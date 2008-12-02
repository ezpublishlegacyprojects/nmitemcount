<?php

$Module = array( "name" => "Add to basket" );

$ViewList 						= array();
$ViewList["addtobasket"] 		= array('functions' => array( 'addtobasket' ),
										"script" => "addtobasket.php", 
										"params" => array("ProductObjectID", "ItemCount"));

$FunctionList['addtobasket'] 	= array( );

?>
