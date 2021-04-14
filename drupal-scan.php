<?php
// ==============================================
// CONFIG
// ==============================================

// URLs to scan
$target = "https://www.target.com";

// nb of modules to scan.
// set to 0 if you wan to scan all existing drupal modules (there are ~ 10.000 modules)
// Modules are scanned in their popularity order. So if you set the value to 100, only the
// 100 most popular modules will be scanned.
$nb_modules_to_scan = 500;

// Path to the executable 'curl'
$curl_command = '/usr/bin/curl';

// Temp file
$tmp_file = "/dev/null";

// Sites urls to scan
$targets = array(
    $target . "/modules/contrib",
//    $target . "/sites/default/modules/contrib",
//    $target . "/sites/all/modules/contrib",
);

// whether to display where the module has been found
$displayDetails = false;

// ==============================================
// List of modules
// ==============================================

// URL of project list
$url_module_usage_base_url = "https://drupal.org";
$url_module_usage = $url_module_usage_base_url . "/project/usage";

// get the list of all project names
echo "\nLoading project list ... ";
$projects = array();
// drupal.org seems to block php_curl simple requests. so let's try another way
$page_size = 100;
$nb_pages = ceil($nb_modules_to_scan / 100);
for ($i = 0; $i < $nb_pages; $i++) {
    $command =$curl_command . ' -L ' . $url_module_usage . "?page=" . $i . " 2>" . $tmp_file;
    ob_start();
    passthru($command);
    $html = ob_get_contents();
    ob_end_clean();

    // Parsing the list in order to get more informations
    $dom = new domDocument();
    @$dom->loadHtml($html);
    $table = $dom->getElementById("project-usage-all-projects");
    $tbody = $table->getElementsByTagName("tbody")[0];
    $rows = $tbody->getElementsByTagName("tr");
    $cpt = 0;
    foreach($rows as $row) {
        $project = $row->getElementsByTagName('a')->item(0);
        $project_name = $project->nodeValue;
        $url = $url_module_usage_base_url . $project->getAttribute('href');
        $pos_slash = strrpos($url, "/");
        $project_id = substr($url, $pos_slash + 1);
        $projects[] = array("id" => $project_id, "name" => $project_name);
        if ($nb_modules_to_scan != 0 && count($projects) == $nb_modules_to_scan) {
            break;
        }
    }
}
$nb_projects = count($projects);
echo $nb_projects . " projects\n\n";

// ==============================================
// Start scan
// ==============================================

$cpt = 0;
$message = "";
$ch = curl_init();
$cpt_found = 0;
foreach ($projects as $project) {
    foreach ($targets as $target) {
        $url = $target . "/" . $project["id"] . "/" . $project["id"] . ".info.yml";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.0; rv:24.0) Gecko/20100101 Firefox/24.0");
        $content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code != 404) {
            // can be either 200 or 403: in both case, that mean the folder exists
            // If 200, we grab the version !
            $version = "";
            if ($http_code == 200) {
                preg_match('/^version *:(.*)$/m', $content, $result);
                if (count($result) >= 2) {
                    $version = " " . trim($result[1], ' \t\'"');
                }
            }
            echo str_repeat(chr(8), strlen($message)); // backspace
            echo $project["name"] . $version . ($displayDetails ? (" (" . $url . ")") : "") . "\n";
            echo $message;
            $cpt_found++;
        }
    }
    $cpt++;
    if ($cpt % 5 == 0) {
        echo str_repeat(chr(8), strlen($message)); // backspace
        $message = $cpt . " / " . count($projects);
        echo $message;
    }
}
curl_close($ch);

echo "\n\n$cpt_found modules found.\n";
