<?php

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
// \REDCap::allowUsers(['reedcw1', 'site_admin']);
echo "<pre>";

$record = json_decode(\REDCap::getData('118580', 'json', '93689-2'));
print_r($record);

echo "</pre>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>