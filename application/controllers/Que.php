<?php

// Load Composer's autoloader for using external libraries
require APPPATH . 'libraries/vendor/autoload.php'; 
require_once APPPATH . 'libraries/mpdf/vendor/autoload.php';

use Clegginabox\PDFMerger\PDFMerger;

set_time_limit(0);
ini_set('memory_limit', -1);
ini_set('max_execution_time', 0);
ini_set("pcre.backtrack_limit", "500000000");

defined('BASEPATH') OR exit('No direct script access allowed');

class Que extends CI_Controller {
 
    // Main entry point for the controller
    public function index(){
        // $this->db->query("UPDATE report_list SET status = 'qweqwe'");
        // sleep(1); // Delaying execution for 5 seconds (could be for load balancing or delay purpose)
        
        // Check if there are pending, ongoing or rendering reports
        $has_pending = $this->db->query("SELECT id FROM report_list WHERE status = 'ongoing' ")->num_rows();
        $print_ongoing = $this->db->query("SELECT id FROM report_list WHERE status = 'ongoing' ")->num_rows();
        $print_rendering = $this->db->query("SELECT id FROM report_list WHERE status = 'rendering' ")->num_rows();
        
        // If there are no ongoing or rendering reports, initiate the DTR processing and generation
        if ($print_ongoing == 0 && $print_rendering == 0) {
            $this->init_process_dtr();  // Initialize and process DTR tasks
        }

        if($has_pending == 0){
            // Run the calculate
            $this->init_calculate();
        }
    }

    // Task to reprocess attendance logs based on a schedule
    public function init_calculate(){
        $emp_list = $this->worker_model->fetch_emp_calculate();  // Fetch list of employees with attendance tasks
        
        // Loop through each employee and reprocess their attendance if applicable
        if ($emp_list->num_rows() > 0) {
            foreach ($emp_list->result_array() as $row) {
                try{
                    $this->worker_model->update_calculate_status($row, "ongoing");

                    $employeeid = $row["employeeid"];
                    $dfrom = $row["dfrom"];
                    $dto = $row["dto"];
                    $this->calculate_attendance($employeeid, $dfrom, $dto); // Calculate attendance for each employee
                }catch (Exception $e) {
                    // SOME ERROR HANDLER HERE
                }
            }
        }
    }

    // Initialize processing of Daily Time Record (DTR) reports
    public function init_process_dtr(){
        $reports = $this->worker_model->get_report_task(); // Get pending DTR report tasks
        
        if ($reports->num_rows() > 0) {
            foreach($reports->result() as $report){
                $this->process_dtr($report); // Process the DTR report for the first task found
            }
        }
    }
	
	public function init_clean_up(){
		$reports = $this->worker_model->stuck_report_list();
		if ($reports->num_rows() > 0) {
            foreach($reports->result() as $report){
                $this->process_clean_up($report); // Process the DTR report for the first task found
            }
        }
	}

    // Calculate attendance for a specific employee and date
    public function calculate_attendance($employeeid, $dfrom, $dto){
        // Prepare data for the API request to calculate attendance
        $curl_uri = getenv('CONFIG_BASE_URL')."/index.php/";
        $form_data = array(
            "client_secret" => "Y2M1N2E4OGUzZmJhOWUyYmIwY2RjM2UzYmI4ZGFiZjk=",
            "username" => "hyperion",
            "password" => "@uaHyperion2024",
            "employeeid" => $employeeid,
            "dfrom" => $dfrom,
            "dto" => $dto
        );
        
        // Set up cURL request to external API
        ini_set('display_errors', 1);
        error_reporting(-1);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $curl_uri . "Api_/calculate_attendance");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1 );
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($form_data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Accept" => "application/json"));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // Log the calculated attendance result to the database
        $calc_history = array(
            "status" => $response,
            "employeeid" => $employeeid,
            "dfrom" => $dfrom,
            "dto" => $dto
        );

        // Insert attendance calculation history to the database
        $this->db->insert("att_calc_history", $calc_history);
    }

    // Process the DTR report for a given report task
    public function process_dtr($det){
        // Check if there are any ongoing or rendering reports
        $has_pending_reports = $this->db->query("SELECT id FROM report_list WHERE status IN ('rendering', 'ongoing')")->num_rows();
        if ($has_pending_reports > 0) {
            return; // Exit if there are pending reports
        }

        // Mark the report as ongoing
        $this->worker_model->updateReportStatus($det->id, "", "ongoing");

        // Prepare date range
        $data["actual_dates"] = [$det->dfrom, $det->dto];
        $dates = $this->time->generateMonthDates($det->dfrom);
        // $det->dfrom = $dates["first_date"];
        // $det->dto = $dates["last_date"];

        // Fetch employees for the report
        $employeelist = $this->worker_model->getEmployeeList($det->where_clause);
        if (empty($employeelist)) {
            $this->worker_model->updateReportStatus($det->id, "", "No employee to generate");
            return;
        }

        // Prepare header description
        $header_desc = "From " . date("F d, Y", strtotime($det->dfrom)) . " to " . date("F d, Y", strtotime($det->dto));
        $data['month_of'] = (date("F Y", strtotime($det->dfrom)) === date("F Y", strtotime($det->dto))) 
            ? date("F Y", strtotime($det->dfrom)) 
            : date("F Y", strtotime($det->dfrom)) . ' - ' . date("F Y", strtotime($det->dto));

        $is_cancelled = false;

        foreach ($employeelist as $employee) {
			try {
				// Check if the report was cancelled
				if ($this->worker_model->report_cancelled($det->id) > 0) {
					$is_cancelled = true;
					break;
				}

				// Prepare data for the report
				$isteaching = $this->worker_model->getempteachingtype($employee->employeeid);
				$data['report_id'] =  $det->id;
				$data['campus'] = $employee->campusid;
				$data['employeeid'] = $employee->employeeid;
				$data['attendance'] = $this->worker_model->getEmployeeDTR($employee->employeeid, $det->dfrom, $det->dto, $isteaching);
                $data['dtrcutoff'] = date('F d, Y', strtotime($det->dfrom)) . ' - ' . date('F d, Y', strtotime($det->dto));
                // echo "<pre>";print_r($data['attendance']);die;
                // echo "<pre>";print_r($data);die;

				// Load the appropriate report view
				$report = $this->load->view(
					$isteaching ? 'dtr/teachingDailyTimeReport' : 'dtr/nonteachingDailyTimeReport',
					$data,
					TRUE
				);

				// Prepare report breakdown data
				$report_data = [
					"base_id" => $det->id,
					"employeeid" => $employee->employeeid,
					"campus" => $det->campus,
					"header_desc" => $header_desc
				];
				$data["report_list"] = [["report" => $report]];
				$data["path"] = "files/reports/pdf/{$employee->employeeid}_{$det->id}.pdf";

				// Save the report breakdown and generate the PDF
				$this->worker_model->save_report_breakdown($report_data);
				$this->load->view('dtr/daily_time_report_pdf', $data);
			}catch (Exception $e) {
                $this->worker_model->updateReportStatus($det->id, "", "error encountered");
				continue;
			}
        }

        // Update the report status based on cancellation
        $this->worker_model->updateReportStatus($det->id, "", $is_cancelled ? "cancelled" : "done");
    }

	public function process_clean_up($det){
		$this->worker_model->reset_report_process($det->id);
	}

}
