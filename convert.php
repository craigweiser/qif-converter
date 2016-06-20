<?php
//echo "Starting conversion...<br />";
$oldFileName = $_FILES['csv']['name'];
$bank = $_POST['bank'];
$validBanks = array("spardabank","abnamro","ing","rabobank","bcee","bceeOld");

if (substr($oldFileName,-4) != '.csv') { $error = "Filename should end in .csv!"; }
if (!in_array($bank, $validBanks)) { $error = "Choose a bank!"; }

if (!empty($error)) {
	echo $error;
	exit;
}

$newFileName = substr($oldFileName,0,strlen($oldFileName) - 4) . ".qif";

header("Content-disposition: attachment; filename=$newFileName");
header("Content-type: application/x-qif");

include('run.php');

$filename = $_FILES['csv']['tmp_name'];

echo generateRegister($filename, $bank);

unlink($filename);