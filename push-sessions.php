#!/usr/bin/php

<?php
/**
 * push-sessions.php - #4 after push-packets.php - next: tag-packets.php
 *
 * Created by: Charles Wheelus  -  5/4/17
 */

$time_start = microtime(true);

openlog("sesspush", LOG_PID | LOG_PERROR, LOG_LOCAL1);

require('./project_settings.php');

$conn = pg_connect(CON);

$now=time();
$logfile=fopen(LOGDIR.'push-sessions.log','a+');

chdir(DATADIR);

// prepared statement for postgresql
$result = pg_prepare($conn, "pushsession", "INSERT INTO s (source,filename,uid,stime,orig_ip,resp_ip,orig_port,resp_port"
    .",proto,service,duration,conn_state,missed_bytes,history,orig_bytes,orig_packets,resp_bytes,resp_packets) VALUES ("
    ."$1,$2,$3,to_timestamp($4),$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,$18)");


$query1="SELECT filename FROM samples WHERE (pushed_packets IS NOT NULL) AND (pushed_sessions IS NULL) AND (skip='FALSE') LIMIT ".PUSHSLIMIT;
$result1=pg_query($conn,$query1);
if(pg_num_rows($result1)==0)
{
    $now=time();
    fwrite($logfile,date('m-d-Y-G:i:s',$now)." no files found to process...exiting\n");
    fclose($logfile);
    pg_close($conn);
    exit;
}
else
{
    while($row1 = pg_fetch_row($result1))
    {
        $files[]=$row1[0];
    }
}


//if(DEBUG) print_r($files);

$count=0;
foreach($files as $file)
{
    //print($file."\n");
    if(chdir(WORKDIR.$file))
    {

        $now=time();
        fwrite($logfile,date('m-d-Y-G:i:s',$now)." ".$file." - \n");

        if($fp = fopen('conn.log', 'r'))
        {
            while ( !feof($fp) )
            {
                $line = fgets($fp);
                $first_char=substr($line,0,1);
                if(strcmp($first_char,'#')==0) continue;
                elseif(empty($line)) continue;
                else
                {
                    $columns1=explode("\t",$line);
                    $sess['ts']=$columns1[0];
                    $sess['uid']=$columns1[1];
                    $sess['orig_ip']=$columns1[2];
                    $sess['orig_port']=$columns1[3];
                    $sess['resp_ip']=$columns1[4];
                    $sess['resp_port']=$columns1[5];
                    $sess['proto']=$columns1[6];
                    $sess['service']=$columns1[7];
                    $sess['duration']=$columns1[8];
                    $sess['orig_bytes']=$columns1[9];
                    $sess['resp_bytes']=$columns1[10];
                    $sess['conn_state']=$columns1[11];
                    $sess['local_orig']=$columns1[12];
                    $sess['local_resp']=$columns1[13];
                    $sess['missed_bytes']=$columns1[14];
                    $sess['history']=$columns1[15];
                    $sess['orig_pkts']=$columns1[16];
                    $sess['orig_bytes']=$columns1[17];
                    $sess['resp_pkts']=$columns1[18];
                    $sess['resp_bytes']=$columns1[19];


                    foreach($sess as $key => $attrib)
                    {
                        if(strcmp($attrib,'-')==0)
                            $sess[$key]=NULL;
                    }

                    if(strlen($sess['duration'])==0)
                        $sess['duration']=0;


                    if (!pg_connection_busy($conn))
                    {
                        pg_send_execute($conn, "pushsession",
                            array(
                                PROJECT,$file,$sess['uid'],$sess['ts'],$sess['orig_ip'],$sess['resp_ip'],
                                $sess['orig_port'],$sess['resp_port'],$sess['proto'],$sess['service'],$sess['duration'],
                                $sess['conn_state'],$sess['missed_bytes'],$sess['history'],$sess['orig_bytes'],
                                $sess['orig_pkts'],$sess['resp_bytes'],$sess['resp_pkts']
                        ));
                        $res2 = pg_get_result($conn);
                        $error=pg_last_error($conn);

                        if($error)
                        {
                            $now = time();
                            fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file." ".$error." \n");
                        }

                    }
                    else
                    {
                        print("Unable to update!....trying again in one second\n");
                        sleep(1);
                        if (!pg_connection_busy($conn))
                        {
                            pg_send_execute($conn, "pushsession",
                                array(
                                    PROJECT,$file,$sess['uid'],$sess['ts'],$sess['orig_ip'],$sess['resp_ip'],
                                    $sess['orig_port'],$sess['resp_port'],$sess['proto'],$sess['service'],$sess['duration'],
                                    $sess['conn_state'],$sess['missed_bytes'],$sess['history'],$sess['orig_bytes'],
                                    $sess['orig_packets'],$sess['resp_bytes'],$sess['resp_packets']
                                ));
                            $res2 = pg_get_result($conn);
                            $error=pg_last_error($conn);

                            if($error)
                            {
                                $now = time();
                                fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file." ".$error." \n");
                            }
                        }
                        else
                        {
                            print("Unable to update AGAIN! (wah wah WAH!)\n");
                        }
                    }
                }
            }
            fclose($fp);
            $now=time();
            $query2="UPDATE samples SET pushed_sessions = '".date('m-d-Y G:i:s',$now)."' where filename='".$file."'";
            $results2=pg_query($conn,$query2);
            if($results2)
            {
                $now = time();
                fwrite($logfile, date('m-d-Y-G:i:s', $now)." ".$file.": pushed sessions \n");
            }
        }
        else
        {
            fwrite($logfile,"Unable to open file: ".$file."/conn.log\n");
            continue;
        }



    }
    else
    {
        fwrite($logfile,"Unable to change directory: ".$file."\n");
        continue;
    }
    $count++;
}

fclose($logfile);
pg_close($conn);
$time_finish = microtime(true);
$total_time = $time_finish - $time_start;

syslog(LOG_INFO, "files=".$count." seconds=".$total_time." limit=".PUSHSLIMIT."\n");
closelog();

?>
