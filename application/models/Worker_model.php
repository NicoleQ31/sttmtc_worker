<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Worker_model extends CI_Model {

	public function fetch_emp_calculate()
	{           
        $this->db->where("status", "pending");
        $this->db->limit(1);
		return $this->db->get("employee_to_calculate");
	}

    public function get_report_task()
	{           
        $this->db->where("status", "pending");
        $this->db->order_by("timestamp", "ASC");
        $this->db->limit(1);
		return $this->db->get("report_list");
	}

    public function displaySched($eid="",$date = ""){
        $wc = "";
        $latestda = date('Y-m-d', strtotime($this->getLatestDateActive($eid, $date)));
        if($date >= $latestda) $wc .= " AND DATE(dateactive) = DATE('$latestda')";

        $query = $this->db->query("SELECT dateactive FROM employee_schedule_history WHERE employeeid = '$eid' AND idx  = DATE_FORMAT('$date','%w') AND DATE(dateactive) <= DATE('$date') $wc ORDER BY dateactive DESC,starttime DESC LIMIT 1;");
        if($query->num_rows() > 0){
            $da = $query->row(0)->dateactive;
          
            $query = $this->db->query("SELECT * FROM employee_schedule_history WHERE employeeid = '$eid' AND idx  = DATE_FORMAT('$date','%w') AND DATE(dateactive) <= DATE('$date') AND dateactive = '$da' GROUP BY starttime,endtime ORDER BY starttime;"); 
        }
        return $query; 
    }
    
    public function getLatestDateActive($employeeid, $date){
		$query = $this->db->query("SELECT dateactive FROM employee_schedule_history WHERE employeeid = '$employeeid' AND DATE(dateactive) <= DATE('$date') ORDER BY dateactive DESC LIMIT 1");
    	if($query->num_rows() > 0) return $query->row()->dateactive;
    	else return false;
    }

    public function getEmployeeList($where = "", $orderBy = ""){
        return $this->db->query("SELECT 
        CONCAT(a.lname, ', ', a.fname , ' ', a.mname) AS fullname,
        a.employeeid, 
        a.fname, 
        a.lname, 
        SUBSTRING(a.`mname`, 1, 1) as mname, 
        b.`description` as department, 
        TRIM(c.`description`) 
        as position_desc, 
        d.description as employement_desc,
        a.campusid
        FROM employee a 
        LEFT JOIN `code_department` b on a.`deptid` = b.`code` 
        LEFT JOIN `code_position` c on a.`positionid` = c.`positionid` 
        LEFT JOIN `code_status` d on a.`employmentstat` = d.`code`
        WHERE 1 = 1 $where ORDER BY fullname ASC")->result();
    }

    public function getempteachingtype($user = ""){
        $return = false;
        $query = $this->db->query("SELECT teachingtype FROM employee WHERE employeeid='$user'");
        if($query->num_rows() > 0)  $return = ($query->row(0)->teachingtype == "teaching" ? true : false);
        return $return;    
    }

    public function getEmployeeDTR($employeeid, $datesetfrom, $datesetto, $isteaching) {
        // Determine the correct table based on employee's teaching type
        $table = $isteaching ? "employee_attendance_teaching" : "employee_attendance_nonteaching";
        
        // Prepare the date range
        $date_range = $this->displayDateRange($datesetfrom, $datesetto);
        $date_list = array_map(function($date) {
            return "'".$date->dte."'"; // Format dates properly for SQL query
        }, $date_range);
    
        // If no dates exist in range, return empty array
        if (empty($date_list)) {
            return [];
        }
    
        // Get all attendance data for the employee in one query
        $date_str = implode(",", $date_list); // Convert the array of dates into a comma-separated string
        $query = "SELECT * FROM $table WHERE employeeid = '$employeeid' AND `date` IN ($date_str) ORDER BY `date`, `id`";
        $results = $this->db->query($query)->result();
    
        // Group attendance data by date
        $attendance = [];
        foreach ($results as $row) {
            $attendance[$row->date][] = $row; // Grouping by date
        }
    
        // Ensure that all dates in the date range are included in the result
        foreach ($date_range as $date) {
            if (!isset($attendance[$date->dte])) {
                $attendance[$date->dte] = []; // If no data for the date, initialize it as an empty array
            }
        }
    
        return $attendance; // Return grouped attendance data, including empty dates
    }

    public function displayDateRange($dfrom = "",$dto = ""){
        $date_list = array();
        if($dfrom && $dto){
            $period = new DatePeriod(
                new DateTime($dfrom),
                new DateInterval('P1D'),
                new DateTime($dto." +1 day")
            );
            foreach ($period as $key => $value) {
                $date_list[$key] = array();
                $date_list[$key] = (object) $date_list[$key];
                $date_list[$key]->dte = $value->format('Y-m-d')    ;   
            }
        }
        
        return $date_list;
    }

    public function getEmployeeName($empid){
		$query = $this->db->query("SELECT CONCAT(lname, ', ', fname , ' ', mname) AS fullname FROM employee WHERE employeeid = '$empid' ");
		if($query->num_rows() > 0) return $query->row()->fullname;
		else return false;
	}

    public function getEemployeeCurrentData($employeeid, $column, $dateformat=''){
    	$query = $this->db->query("SELECT $column FROM employee WHERE employeeid = '$employeeid' ");
    	if($query->num_rows() > 0){
    		if($dateformat){
    			if($query->row()->$column != '' && $query->row()->$column != '0000-00-00' && $query->row()->$column != '1970-01-01') return date($dateformat, strtotime($query->row()->$column));
    			else return false;
    		}else{
    			return $query->row()->$column;
    		}
    	}
    	else return false;
    }

    public function getEmployeeDepartment($employeeid){
    	$q_dept = $this->db->query("SELECT description FROM employee a INNER JOIN code_department b ON a.`deptid` = b.`code` WHERE employeeid = '$employeeid' ");
    	if($q_dept->num_rows() > 0) return $q_dept->row()->description;
    	else return "Not assigned";
    }

    public function getemployeestatus($empstatus=""){
        $returns = "";
        $q = $this->db->query("SELECT code,description FROM code_status WHERE code='$empstatus'")->result();
        foreach($q as $row){
            $returns = $row->description;
        }
        return $returns;
    }
    
    public function updateReportStatus($report_id, $path, $status="done"){
        $this->db->where("id", $report_id);
        $this->db->set("status", $status);
        if($status == "done"){
            $this->db->set("path", $path);
            $this->db->set("done_time", $this->getServerTime());
        }
        $this->db->update("report_list");
    }

    public function save_report_breakdown($report_list){
        // $this->db->where("employeeid", $report_list["employeeid"]);
        // $this->db->where("base_id", $report_list["base_id"]);
        // $this->db->select("employeeid");
        // $is_exists = $this->db->get("report_breakdown")->num_rows();

        // if($is_exists === 0){
            $this->db->insert("report_breakdown", $report_list);
        // }
    }

    public function fetch_dtr($id){
        return $this->db->query("SELECT b.* FROM report_list a INNER JOIN report_breakdown b ON a.id = b.base_id WHERE a.status = 'rendering' AND b.base_id = '$id' ")->result_array();
    }

    public function get_processed_report()
	{           
        $this->db->where("status", "rendering");
        $this->db->order_by("timestamp", "ASC");
        $this->db->limit(1);
		return $this->db->get("report_list");
	}

    public function getServerTime(){
		$query_time = $this->db->query("SELECT CURRENT_TIMESTAMP ")->row()->CURRENT_TIMESTAMP;
		return $query_time;
	}

    public function report_cancelled($id){
        return $this->db->query("SELECT id FROM report_list WHERE status = 'cancelled' AND id = '$id'")->num_rows();
    }
    
    public function update_calculate_status($filter, $status="done"){
		$this->db->where($filter);
		$this->db->set("status", $status);
		$this->db->update("employee_to_calculate");
	}

    public function stuck_report_list(){
        return $this->db->query("SELECT id FROM report_list 
                                    WHERE TIMESTAMP <= NOW() - INTERVAL 10 MINUTE 
                                    AND STATUS = 'ongoing' 
                                            AND done_time = '' ORDER BY timestamp ASC LIMIT 1");
    }
    
    public function reset_report_process($id) {
        // Update the 'status' column to 'pending' for the given report ID
        $this->db->set('status', 'pending');
        $this->db->where('id', $id);
        $this->db->update('report_list'); // Update the 'report_list' table
    
        // Check if the update was successful before proceeding
        if ($this->db->affected_rows() > 0) {
            // If the update was successful, delete related records from 'report_breakdown' table
            $this->db->where('base_id', $id);
            $this->db->delete('report_breakdown'); // Delete records with matching 'base_id'
        }
    }
    
}