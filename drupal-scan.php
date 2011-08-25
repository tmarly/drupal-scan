<?php
  // ==============================================
  // CONFIG
  // ==============================================

  // URLs to scan
  $target = "http://www.target.com";

  // nb of modules to scan. 
  // set to 0 if you wan to scan all existing drupal modules (there are ~ 10.000 modules)
  // Modules are scanned in their popularity order. So if you set the value to 100, only the
  // 100 most popular modules will be scanned.
  $nb_modules_to_scan = 500; 
  
  // ==============================================
  // Sites urls (default, all, ...)
  // ==============================================

  $url = parse_url($target);
  $host = $url["host"];
  $ch = curl_init();
  $conf_folder = "default";
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_NOBODY, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
  do {
    curl_setopt($ch, CURLOPT_URL, "$target/sites/$host/modules/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    $header = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code != 404) {
      // folder exist, so this is where modules will be.
      $conf_folder = $host;
      break;
    }
    $pos = strpos('.', $host);
    if ($pos !== false) {
      $host = substr($host, $pos + 1);
    }
  } while ($pos);
  curl_close($ch);
  
  $targets = array(
    $target . "/sites/" . $conf_folder . "/modules",
    $target . "/sites/all/modules",
  );
    
  echo "\nTargets:\n";
  foreach($targets as $target) {
    echo "  " . $target . "\n";
  }
  
  // ==============================================
  // List of modules
  // ==============================================

  // URL of project list
  $url_module_usage_base_url = "http://drupal.org";
  $url_module_usage = $url_module_usage_base_url . "/project/usage";
  
  // get the list of all project names
  echo "\nLoading project list ... ";
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url_module_usage);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $html = curl_exec($ch);
  curl_close($ch);
  
  // Parsing the list in order to get more informations
  $projects = array();
  $dom = new domDocument();
  @$dom->loadHtml($html);
  $table = $dom->getElementById("project-usage-all-projects");
  $rows = $table->getElementsByTagName("tr");
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
      $url = $target . "/" . $project["id"] . "/";
      curl_setopt($ch, CURLOPT_URL, $url); 
      curl_setopt($ch, CURLOPT_HEADER, true);
      curl_setopt($ch, CURLOPT_NOBODY, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
      $header = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
       
      if ($http_code != 404) {
        // can be either 200 or 403: in both case, that mean the folder exists
        echo str_repeat(chr(8), strlen($message)); // backspace
        echo $url . " \t" . $project["name"] . "\n";
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
  