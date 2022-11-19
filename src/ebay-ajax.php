<?php
    $v = $_GET['v'];
    $q = urlencode($_GET['q']);

		$res = file_get_contents("https://autosug.ebay.co.uk/autosug?_dg=1&sId=0v=$v&kwd=$q");
		$res = str_replace("/**/vjo.darwin.domain.finding.autofill.AutoFill._do(", "",$res);
		$res = str_replace(")","",$res);
		echo $res;
?>
