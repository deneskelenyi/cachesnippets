<?php
/*

This is a snippet from a Wordpress site. Do not expect this to work for you as it is!
This is just an example I wrote a long time ago and if it helps someone: great.

The "some_event" is a name I changed from something that would identify a specific site. 
It is reference of a real time event, such as a TV programme (run_date is the starting date/time), a sports event, 
or an other event like the Oscars. 

Again, this is a snippet that might give some ideas. This works fine without attendance for several years now somewhere. 

*/


/**
 * Use this to see if a local data row was saved. This returns the run_date (originally beongs to an event) to see how long ago it was.
 * 
 * @param mixed $id
 * 
 * @return [array]
 */
function check_local_some_data($id)   
{
    global $wpdb;
        $check = -1;
        $sql = "select *,unix_timestamp(now())-unix_timestamp(run_date) agoTS  from ext_some_data where some_data_id=%s;";
        $rs = $wpdb->get_results($wpdb->prepare($sql, array($id)));
        if (count($rs) > 0) {   
                return array("error" => 0, "rs" => $rs[0]);
        } else {
                return array("error" => 1, "rs" => null);
        }

}

/**
 * try to get a file from the cache.
 * 
 * Why the intdiv? 
 * If you have thousands, or millions of events, you will end up with a directory that's very hard to parse, list, <process>
 * using intdiv on the id number (which is the file name), each file is sorted into it's "beginning directory" essentially limiting 
 * Evaluate the number of events (IDs) you will have, and possibly create further directories accordingly.
 * Also check what ID numbers you (will) have and plan this accordingly. On Linux "debugfs" is your friend to determine 
 * maximum directory size. 
 * 
 * 2 have multiple levels, here is a quick test. You can also iterate is nicer, but this will take care of pretty numbers 
 * as it is, without causing file system problems (on Linux)
 * 
 * for($x=0; $x<100; $x++){
 *   $randnum = rand(1,9999999999999);
 *   $a = intdiv($randnum,10000000);
 *   $b = intdiv($randnum,10000)-1000*$a;
 *   echo "/$a/$b/$randnum";
 *   echo "\n";
 *  }
 * 
 * 
 * 
 * @param mixed $some_ctid
 * 
 * @return [type]
 */
function check_local_some_data_file($some_ctid)
{
        $dir = intdiv($some_ctid, 1000);
        $dir = "./wp-content/themes/some/cache/" . $dir;
        $full_path = $dir . "/" . $some_ctid;
        $ret = array();
        $sd = null;
        if (file_exists($full_path)) {
                try {
                        $sd = json_decode(file_get_contents($full_path), true);
                        $ret["content"] = $sd;
                } catch (Exception $ex) {
                        $sd = null;
                        $ret["error_content"] = 1;
                        $ret["error"] = 1;
                }
                if (count($sd["dct"]) > 0) {
                        $ret["error"] = 1;
                        $ret["error_content"] = 0;
                }
        } else {
                $ret["error"] = 1;
                $ret["error_content"] = 1;
        }
        return $ret;
}

/**
 * @param mixed $some_ctid
 * @param mixed $sd
 * 
 * @return [type]
 */
function save_local_some_data_file($some_ctid, $sd)
{
        $dir = intdiv($some_ctid, 1000);
        $dir = "./wp-content/themes/some/cache/" . $dir;
        $full_path = $dir . "/" . $some_ctid;
        umask(0);
        if (!file_exists($dir)) {
                mkdir($dir);
        }
        file_put_contents($full_path, json_encode($sd));
}


/**
 * @param mixed $some_ctid
 * @param mixed $status
 * @param mixed $dct
 * 
 * @return [type]
 */
function save_local_some_data($some_ctid, $status, $dct)
{
        global $wpdb;
        $sql = "insert  into ext_some_data  ....    ;";          // your sql to insert
        $params = array($some_ctid,$status);      // your params
        $wpdb->query($wpdb->prepare($sql, $params));
}

/**
 * @param mixed $some_ctid
 * 
 * @return [type]
 */
function get_some_single_data_local_cached($some_ctid)
{
        global $debug, $wpdb;

        $db_check = check_local_some_data($some_ctid);
        if ($db_check["error"] == 1) {
                
                $json = get_some_single_data($some_ctid);
                $dct = $json["dct"];                
                if (is_array($dct)) {
                        if (count($dct) > 0) {
                                save_local_some_data($some_ctid, 1, $dct);
                                save_local_some_data_file($some_ctid, $json);
                        }
                } else {
                        
                        if ($json["max_some_ctt_id"] > $some_ctid) {
                                
                                save_local_some_data($some_ctid, -1, null);
                        } else {
                                //error handling ?
                        }
                }
        } elseif ($db_check["error"] == 0) {

                if ($db_check["rs"]->status_avail == -1) {
                        return null;
                } elseif ($db_check["rs"]->status_avail > -1) {
                        
                        if ($db_check["rs"]->agoTS > 86400) { // older than a day
                                
                                $file_check = check_local_some_data_file($some_ctid);
                                
                                if ($file_check["error_content"] == 0) {
                                        //error content error is zero - good 
                                        if (is_array($file_check["content"])) {
                                                return $file_check["content"];
                                        } else {
                                                return null;
                                        }
                                } else {
                                        return null;
                                }
                            } else {
                                
                                $json = get_some_single_data($some_ctid);
                                $dct = $json["dct"];
                                if (is_array($dct)) {
                                        if (count($dct) > 0) {
                                                save_local_some_data($some_ctid, 1, $dct);
                                                save_local_some_data_file($some_ctid, $json);
                                        }
                                } else {
                                        
                                        return null;
                                }
                        }
                }
        }



        return $json;
}




/**
 *  When there is no local, or too old, and no cache, go to your API and get it
 * @param mixed $some_ctid
 * 
 * @return [array] // from json
 */
function get_some_single_data($some_ctid)
{
        global $debug;
        $url = "http://some_api_url.com/some_single_data_router.php?id=" . $some_ctid;
        if ($debug) { 
                echo $url;;
        }
        $data = getApiURL($url, 7200);
        $json = json_decode($data, true);
        return $json;
}



/**
 * This function checks if we have a stored json document for the given key.
 * If not, then it will fetch it from the api.
 * Please note the urlencode, that seems to work. When using an un-encoded URL as a key, it causes problems.
 * Also, use verify, this is for a self-signed certificate.
 * 
 * @param mixed $url
 * @param mixed $cachetime
 * 
 * @return [type]
 */
function getApiURL($url, $cachetime)
{
        global $nocache, $debug;
        if (0 == $nocache) {
                $result = mcache_get(urlencode($url));
                if ($debug) echo "CACHED";
        }
        if (!isset($result)) {
                if($debug) echo "NOT CACHED";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_URL, $url);
                $result = curl_exec($ch);
                mcache_set(urlencode($url), $result, $cachetime);
        }
        return $result;
}

/**
 * You could use a global Memcached, and it *might* work. Until on a server it doesn't.
 * @param mixed $key
 * @param mixed $val
 * @param mixed $expire
 * 
 * @return [type]
 */
function mcache_set($key, $val, $expire)
{
        $m = new Memcached();
        $m->addServer('localhost', 11211);        
        $m->set($key, $val, $expire);

}
/**
 * You could use a global Memcached, and it *might* work. Until on a server it doesn't.
 * @param mixed $key
 * 
 * @return [type]
 */
function mcache_get($key)
{        
        $m = new Memcached();
        $m->addServer('localhost', 11211);
        $data = $m->get($key);
        if ($data == "") return null;
        return $data;

}


