#!/usr/bin/php5
<?php

/**
 *
 */

$speedtest = new speedtest();

die();
class speedtest
{

    private $maxrounds = 4;
    private $downloads = "temp_down/";
    private $uploads = "upload/";
    private $datadir = "data/";
    private $useragent = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13';
    private $speedtestServersUrl = 'http://www.speedtest.net/speedtest-servers.php';
    private $speedtestServersFile = 'testservers.xml';
    private $countryCode = 'ZA';
    private $do_size = array(1 => 500, 2 => 1000, 3 => 1500, 4 => 2000, 5 => 2500, 6 => 3000, 7 => 3500, 8 => 4000);
    private $randoms = null;
    private $time = null;
    private $day = null;
    private $do_server = array();

    private $globallatency = 0;
    private $globaldownloadspeed = 0;
    private $latencies = array();

    private $ch = null;

    public function __construct()
    {
        $this->randoms = rand(100000000000, 9999999999999);
        $this->time = time();
        $this->day = date("d-m-Y");
        $this->getServers();
        $this->testLatency();
        $this->testDownload();
    }

    private function getServers()
    {

        print "Getting list of speedtest.net servers..." . PHP_EOL;

        if (file_get_contents($this->speedtestServersFile) == '') {


            $fp = fopen($this->speedtestServersFile, 'w');
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->speedtestServersUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->useragent);
            $contents = curl_exec($ch);
            $info = curl_getinfo($ch);

            print "Got list: " . round($info['size_download'] / pow(1024, 1), 2) . "KB" . PHP_EOL;

            curl_close($ch);
            fclose($fp);

            // code...
        }

        $xml = simplexml_load_string(file_get_contents($this->speedtestServersFile));


        $servers = $xml->xpath('/settings/servers/server[@cc="' . $this->countryCode . '"]');


        foreach ($servers as $server) {
            $url = parse_url((string)$server['url']);
            $this->do_server[] = array(
                'name' => (string)$server['sponsor'] . " - " . (string)$server['name'],
                'url' => $url['scheme'] . '://' . $url['host'],
                'urlParts' => $url
            );

        }

        print "Got " . count($this->do_server) . " servers for country code " . $this->countryCode . PHP_EOL;
    }

    private function testLatency()
    {
        foreach ($this->do_server as $server => $serverdetails) {
            $this->globallatency = 0;
            print "* Testing latency for " . $serverdetails['name'] . "... " . PHP_EOL;
            $this->latency($this->maxrounds, $serverdetails);
            $this->latencies[$server] = $this->globallatency / $this->maxrounds;
            $this->globallatency = 0;
            print PHP_EOL;
        }
        asort($this->latencies);
        $this->latencies = array_slice($this->latencies, 0, 5, true);

        print "keeping the 5 servers that responded the fastest:" . PHP_EOL;
        foreach ($this->latencies as $key => $value) {
//            print $this->ping(['urlParts']['host']) . PHP_EOL;
            print $this->do_server[$key]['name'] . " at " . $value . "ms" . PHP_EOL;
        }
    }

    private function testDownload()
    {
        foreach ($this->latencies as $key => $value) {
            $this->globaldownloadspeed = 0;
            print "* Testing download speed for " . $this->do_server[$key]['name'] . "..." . PHP_EOL;
            $this->download(1, $this->maxrounds, $this->do_server[$key]);
        }
    }

    private function latency($round, $serverdetails)
    {
        $file = $this->downloads . "latency.txt";
//        $fp = fopen($file, 'w+');
//        $ch = curl_init($serverdetails['url'] . "/speedtest/latency.txt?x=" . $this->randoms);
//        curl_setopt($ch, CURLOPT_HEADER, true);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
//        curl_setopt($ch, CURLOPT_FILE, $fp);
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
//
//        $response = curl_exec($ch);
//        $duration = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
        $duration = $this->ping($serverdetails['urlParts']['host']);

//        curl_close($ch);
//        fclose($fp);
//        unlink($file);

        print round($duration, 2) . "ms ";

        $this->globallatency += $duration;
        if ($round > 1) {
            $this->latency(--$round, $serverdetails);
        } else {
            print "\tAverage:" . round($this->globallatency / $this->maxrounds, 2) . "ms ";
        }
    }

    private function download($size, $round, $serverdetails)
    {
        global $globaldownloadspeed;

        $file = $this->downloads . "fails_" . $size . ".jpg";

        $fp = fopen($file, 'w+');
        $ln = $serverdetails['url'] . "/speedtest/random" . $this->do_size[$size] . "x" . $this->do_size[$size] . ".jpg?x=" . $this->randoms . "-" . $size;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ln);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);

        $duration = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $downloadSize = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        $downloadSpeed = curl_getinfo($ch, CURLINFO_SPEED_DOWNLOAD);
        $downloadSpeed = ((($downloadSpeed * 8) / 1000) / 1000);

        // echo "Curl says avg DL speed was " . $downloadSpeed . PHP_EOL;

        if ($response === false) {
            //print "Request failed: ".curl_error( $ch ) . PHP_EOL;
        }
        curl_close($ch);
        fclose($fp);

        //unlink($file);

        if ($duration < 4 && $size != 8) {
            $this->download(++$size, $round, $serverdetails);
        } else {
            logResults(round($downloadSpeed, 2), "d");
            if ($duration < 4) {
//                print "Duration is " . round($duration, 2) . "sec - this may introduce errors." . PHP_EOL;
            }
            print round($downloadSize / pow(1024, 2), 2) . "MB took " . round($duration, 2) . " seconds at " . round($downloadSpeed, 2) . "Mbps" . PHP_EOL;
            $this->globaldownloadspeed += $downloadSpeed;
            if ($round > 1) {
                $this->download($size, --$round, $serverdetails);
            } else {
                print "\tAverage: " . round($this->globaldownloadspeed / $this->maxrounds, 2) . " Mbps." . PHP_EOL;
            }
        }
    }

    private function ping($host)
    {
        $package = "\x08\x00\x19\x2f\x00\x00\x00\x00\x70\x69\x6e\x67";

        /* create the socket, the last '1' denotes ICMP */
        $socket = socket_create(AF_INET, SOCK_RAW, 1);

        $sec = 0;
        $usec = 500 * 1000;

        /* set socket receive timeout to 1 second */
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" =>$sec, "usec" => $usec));

        /* connect to socket */
        socket_connect($socket, $host, null);

        /* record start time */
        list($start_usec, $start_sec) = explode(" ", microtime());
        $start_time = ((float)$start_usec + (float)$start_sec);


        socket_send($socket, $package, strlen($package), 0);

        if (@socket_read($socket, 255)) {
            list($end_usec, $end_sec) = explode(" ", microtime());
            $end_time = ((float)$end_usec + (float)$end_sec);

            $total_time = $end_time - $start_time;

            return round($total_time * 1000,2);
        } else {
            return round((((float)$sec  * 1000)+((float)$usec / 1000)),2);
        }

        socket_close($socket);
    }

}

/*
 *  Speedtest.net linux terminal client.
 *  This is free and open source software by Alex based on a script from Janhouse
 *  Script uses curl, executes ifconfig commands in shell and writes temporary files in temp_down folder. Make sure you have everything set up before using it.
 */
header("content-type: text/plain");
/* * *              Configuration                   * * */
$maxrounds = 4;
$downloads = "temp_down/";
$uploads = "upload/";
$datadir = "data/";
/* * *              Speedtest servers               * * */

$do_server = array();

print "Getting list of speedtest.net servers..." . PHP_EOL;

$fp = fopen('testservers.xml', 'w');
$ln = 'http://speedtest.net/speedtest-servers.php';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ln);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
$contents = curl_exec($ch);
$info = curl_getinfo($ch);

print "Got list: " . round($info['size_download'] / pow(1024, 1), 2) . "KB" . PHP_EOL;

curl_close($ch);
fclose($fp);

$xml = simplexml_load_string(file_get_contents('testservers.xml'));


$servers = $xml->xpath('/settings/servers/server[@cc="ZA"]');


foreach ($servers as $server) {
    $url = parse_url((string)$server['url']);
    $do_server[] = array(
        'name' => (string)$server['sponsor'] . " - " . (string)$server['name'],
        'url' => $url['scheme'] . '://' . $url['host'],
        'urlParts' => $url
    );

}

/* Variables */
$randoms = rand(100000000000, 9999999999999);
$time = time();
$day = date("d-m-Y");
$do_size[1] = 500;
$do_size[2] = 1000;
$do_size[3] = 1500;
$do_size[4] = 2000;
$do_size[5] = 2500;
$do_size[6] = 3000;
$do_size[7] = 3500;
$do_size[8] = 4000;

/* * *              The rest                        * * */
function latency($round)
{
    global $server, $downloads, $do_server, $serverdetails, $iface, $randoms, $do_size, $globallatency, $maxrounds;

    $file = $downloads . "latency.txt";
    $fp = fopen($file, 'w+');
    $ch = curl_init($serverdetails['url'] . "/speedtest/latency.txt?x=" . $randoms);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $duration = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;

    curl_close($ch);
    fclose($fp);
    unlink($file);

    print round($duration, 2) . "ms." . PHP_EOL;

    $globallatency += $duration;
    if ($round > 1) {
        latency(--$round);
    } else {
        print "\tAverage:" . round($globallatency / $maxrounds, 2) . "ms.\n";
    }
}

function download($size, $round)
{
    global $server, $downloads, $do_server, $serverdetails, $iface, $randoms, $do_size, $globaldownloadspeed, $maxrounds;

    $file = $downloads . "fails_" . $size . ".jpg";

    $fp = fopen($file, 'w+');
    $ln = $serverdetails['url'] . "/speedtest/random" . $do_size[$size] . "x" . $do_size[$size] . ".jpg?x=" . $randoms . "-" . $size;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ln);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);

    $duration = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $downloadSize = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
    $downloadSpeed = curl_getinfo($ch, CURLINFO_SPEED_DOWNLOAD);
    $downloadSpeed = ((($downloadSpeed * 8) / 1000) / 1000);

    // echo "Curl says avg DL speed was " . $downloadSpeed . PHP_EOL;

    if ($response === false) {
        //print "Request failed: ".curl_error( $ch ) . PHP_EOL;
    }
    curl_close($ch);
    fclose($fp);

    //unlink($file);

    if ($duration < 4 && $size != 8) {
        download(++$size, $round);
    } else {
        logResults(round($downloadSpeed, 2), "d");
        if ($duration < 4) {
            print "Duration is " . round($duration, 2) . "sec - this may introduce errors." . PHP_EOL;
        }
        print round($downloadSize / pow(1024, 2), 2) . "MB took " . round($duration, 2) . " seconds at " . round($downloadSpeed, 2) . "Mbps" . PHP_EOL;
        $globaldownloadspeed += $downloadSpeed;
        if ($round > 1) {
            download($size, --$round);
        } else {
            print "\tAverage: " . round($globaldownloadspeed / $maxrounds, 2) . " Mbps." . PHP_EOL;
        }
    }
}

function upload($size, $round)
{
    global $server, $uploads, $do_server, $serverdetails, $iface, $randoms, $globaluploadspeed, $maxrounds;

    $file = $uploads . "upload_" . $size;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
    curl_setopt($ch, CURLOPT_URL, $serverdetails['url'] . $serverdetails['urlParts']['path'] . "?x=0." . $randoms);
    curl_setopt($ch, CURLOPT_POST, true);
    $post = array(
        "file_box" => "@" . $file,
    );
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

    $response = curl_exec($ch);

    $duration = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $uploadSize = curl_getinfo($ch, CURLINFO_SIZE_UPLOAD);
    $uploadSpeed = curl_getinfo($ch, CURLINFO_SPEED_UPLOAD);
    $uploadSpeed = ($uploadSpeed * 8) / pow(1000, 2);

    if ($response === false) {
        // print "Request failed: ".curl_error( $ch ) . PHP_EOL;
    }

    if ($duration < 4 && $size != 8) {
        upload(++$size, $round);
    } else {

        logResults(round($uploadSpeed, 2), "u");
        if ($duration < 4) {
            print "Duration is " . round($duration, 2) . "sec - this may introduce errors.\n";
        }
        print round($uploadSize / pow(1024, 2), 2) . "MB took " . round($duration, 2) . " seconds at " . round($uploadSpeed, 2) . "Mbps" . PHP_EOL;
        $globaluploadspeed += $uploadSpeed;
        if ($round > 1) {
            upload($size, --$round);
        } else {
            print "\tAverage: " . round($globaluploadspeed / $maxrounds, 2) . " Mbps.\n";
        }
    }
}

function logResults($data, $updown)
{ // u - upload; d - download
    global $day, $time, $datadir, $iface;
    $fp = fopen($datadir . "data_" . $day . ".txt", "a");
    fwrite($fp, $time . "|" . $updown . "|" . $iface . "|" . $data . "\n");
    fclose($fp);
}

foreach ($do_server as $server => $serverdetails) {
    $globallatency = 0;
    print "* Testing latency for " . $serverdetails['name'] . "..." . PHP_EOL;
    latency($maxrounds);
}
foreach ($do_server as $server => $serverdetails) {
    $globaldownloadspeed = 0;
    print "* Testing download speed for " . $serverdetails['name'] . "..." . PHP_EOL;
    download(1, $maxrounds);
}
foreach ($do_server as $server => $serverdetails) {
    $globaluploadspeed = 0;
    print "* Testing upload speed " . $serverdetails['name'] . "..." . PHP_EOL;
    upload(1, $maxrounds);
}

?>
