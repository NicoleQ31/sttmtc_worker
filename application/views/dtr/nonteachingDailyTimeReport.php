<?php 

    $style = '
    <style>
        .form_no{
            font-size:0.9rem;
        }

        .main-wrapper{
            height:100%;
            width:100%;
            padding-right:25%;
            padding-left:25%;
        }
        .table-header,.sub-header,.main-table{
            width:100%;
            height:100%;
            font-size:18px;
            font-family:calibri;
        }

        .main-table{
            border-collapse:collapse;
            border: 1px solid black;
        }

        .main-table td{
            border: 1px solid black;
            font-size:12px;
        }

        .title{
            font-size:1.5rem;
        }

        .name{
            font-size:1.5rem;
        }

        .tc{
            text-align:center;
            font-size:27px;
        }
        
        .tb{
            font-weight:bold;
        }
        .m10{
            margin-top:10px;
        }
        .header{
            background-color: #65d73a;
            color: white;
        }
        .td-height{
            height: 20px;
        }
    </style>';

    $fullname = $this->worker_model->getEmployeeName($employeeid);
    $campus = $this->worker_model->getEemployeeCurrentData($employeeid, 'campusid');
    $employmentstat = $this->worker_model->getemployeestatus($this->worker_model->getEemployeeCurrentData($employeeid, 'employmentstat'));
    $department = $this->worker_model->getEmployeeDepartment($employeeid);
    
    $content = '
        <table style="width:100%;height:100%;">
            <tr style="margin-right:15px;">';
                $att_mirror='
                <td style="padding-right: 18px;" id="dtr-content">
                    <div class="container main-wrapper">
                        <table class="table-header">
                            <tr><td class="form_no"><i></i></td></tr>
                            <tr><td class="tc title"><img src="images/school_logo_with_desc.png" style="width: 400;"/></td></tr>
                            <tr><td class="tc" style="font-size:15px;"><strong>EMPLOYEE ATTENDANCE</strong></td></tr>
                            <tr><td class="tc" style="font-size:11px;"><strong>'.$dtrcutoff.'</strong></td></tr>
                        </table>
                        <br>

                        <table style="font-size:11px;width:100%">
                            <tr>
                            <td><strong>Employee Name: '.$fullname.'</strong></td>
                            <td><strong>Campus: '.$campus.'</strong></td>
                            </tr>
                            <tr>
                            <td><strong>Employee ID: '.$employeeid.'</strong></td>
                            <td><strong>Office: '.$department.'</strong></td>
                            </tr>
                        </table>


                        <table class="main-table m10" style="font-size:19px;">
                            <tr>
                                <td rowspan="2" class="tc header" style="width:20%">Date</td>
                                <td class="tc header" colspan="2" style="width:20%">Actual Log Time</td>
                                <td class="tc header" rowspan="2" style="width:25%">Remarks/Others</td>
                                <td class="tc header" rowspan="2"  style="width:25%">Holiday</td>
                            </tr>
                            <tr>
                                <td class="tc header">IN</td>
                                <td class="tc header">OUT</td>
                            </tr>
                            ';

                            foreach ($attendance as $date_arr => $att_date) {
                                $AM_arrival = $AM_departure = $PM_arrival = $PM_departure = $hour = $minute = $remarks = "";
                                if($att_date){
                                    $date = $this->time->DayFormatted($date_arr);
                                    $AM_arrival = (isset($att_date[0]) ? $att_date[0]->actlog_time_in : '');
                                    $AM_departure = (isset($att_date[0]) ?$att_date[0]->actlog_time_out : '');

                                    $lastDataIndexPerDate = count($att_date)-1; //Para makuha yung pinaka last out nya 

                                    $PM_arrival = (isset($att_date[$lastDataIndexPerDate]) && count($att_date) > 1 ? $att_date[$lastDataIndexPerDate]->actlog_time_in:'');
                                    $PM_departure = (isset($lastDataIndexPerDate) && count($att_date) > 1 ?$att_date[$lastDataIndexPerDate]->actlog_time_out:'');

                                    $late = $this->time->exp_time($att_date[0]->late);
                                    $undertime = $this->time->exp_time($att_date[0]->undertime);
                                    $total_tardy = $late + $undertime;
                                    $total_tardy = $this->time->sec_to_hm($total_tardy);
                                    [$hour, $minute] = explode(":", $total_tardy);
                                    
                                    $remarks = "";
                                    // $remarks = (isset($att_date[$lastDataIndexPerDate])  ?$this->time->formatTimeOutput($att_date[$lastDataIndexPerDate]->remarks) : '');
                                    if($this->worker_model->displaySched($employeeid, $date_arr)->num_rows() == 0){
                                        // $remarks = "No Shedule";
                                        $remarks = date("l", strtotime($date_arr));
                                    }

                                    // if (strpos($remarks, "PENDING") !== false) {
                                    //     $remarks = "";
                                    // }
                                    
                                    // if (strpos($remarks, "LEAVE") !== false) {
                                    //     $remarks = "LEAVE";
                                    // }elseif (strpos($remarks, "CORRECTION") !== false) {
                                    //     $remarks = "";
                                    // }elseif (strpos($remarks, "COC") !== false) {
                                    //     $remarks = "OVERTIME";
                                    // }elseif (strpos($remarks, "CTO") !== false) {
                                    //     $remarks = "OVERTIME";
                                    // }elseif (strpos($remarks, "SERVICE CREDIT") !== false) {
                                    //     $remarks = "OVERTIME";
                                    // }
                                    
                                    foreach($att_date as $k => $v){
                                        if($v->holiday){
                                            $remarks = "Holiday";
                                        }else if (strpos($v->remarks, "UNDERTIME") !== false){
                                            if($v->actlog_time_in == "--" && strtotime($v->actlog_time_out) >= strtotime($v->off_time_out)){
                                                // $remarks = "";
                                            }
                                        }else if(strpos($v->remarks, "NO TIME IN") !== false){
                                            if($v->off_time_in != "--" && $v->off_time_out != "--"){
                                                if($v->actlog_time_in == "--" && $v->actlog_time_out == "--"){
                                                    // $remarks = "<span style='color:red'>ABSENT</span>"; 
                                                }else if(($v->actlog_time_in == "--" && $v->actlog_time_out != "--") || ($v->actlog_time_in != "--" && $v->actlog_time_out == "--")){
                                                    if($AM_arrival == "" && $PM_departure == ""){
                                                        // $remarks = "<span style='color:red'>UNDERTIME</span>"; 
                                                    }else{
                                                        // $remarks = "";
                                                    }
                                                }
                                            }
                                        }else if(strpos($v->remarks, "OFFICIAL BUSINESS") !== false){
                                            $AM_arrival = $AM_departure = $PM_arrival = $PM_departure = "";
                                        }
                                    }

                                    if($this->time->validateDateBetween($actual_dates, $date_arr) === false){
                                        $AM_arrival = $AM_departure = $PM_arrival = $PM_departure = $hour = $minute = $remarks = "";
                                    }

                                    $date = $this->time->DayFormatted($date_arr);
                                }else{
                                    if($this->worker_model->displaySched($employeeid, $date_arr)->num_rows() == 0){
                                        $AM_arrival = $AM_departure = $PM_arrival = $PM_departure = $hour = $minute = "";
                                        $date = $this->time->DayFormatted($date_arr);
                                        $remarks = date("l", strtotime($date_arr));
                                    }
                                }

                                $hour = $minute = 0;
                                $att_mirror .= '
                                <tr>
                                    <td class="tc" rowspan=2>'.(date("d-M (l)",strtotime($date_arr))).'</td>
                                    <td class="tc td-height">'.$AM_arrival.'</td>
                                    <td class="tc td-height">'.$AM_departure.'</td>
                                    <td class="tc" rowspan=2 >'.$remarks.'</td>
                                    <td> </td>
                                </tr>
                                <tr>
                                    <td class="tc td-height">'.$PM_arrival.'</td>
                                    <td class="tc td-height">'.$PM_departure.'</td>
                                </tr>'
                                ;
                            }

                        $att_mirror .= '
                                <tr>
                                    <td style="font-size:18px;" colspan="5" style="text-align:left;">Total: </td>
                                </tr>
                            </table>
                    </div>
                    <br>
                    
                    <table class="regards" style="width:100%;font-size:11px;">
                        <tr class="align_right">
                            <td>Acknowledged by : </td>
                            <td> _________________________________________ </td>
                            <td>Certified by : </td>
                            <td> _________________________________________ </td>
                        </tr>
                    </table>


                </td>';

            $content = $content.$att_mirror;

           $content.=' </tr>
        </table>';
        echo $style.$content;
?>