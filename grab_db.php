<?php
$dir_path=getcwd ();
require_once("config_main.php");
// define('ROOT_PATH', MAIN_CONFIG_PATH."/"); 
echo "<br />Changing path to ".MAIN_CONFIG_PATH;
chdir(MAIN_CONFIG_PATH);
require_once("config.php"); 
echo "<br />CUSTOM_CONTENT_PATH: ".CUSTOM_CONTENT_PATH;
// chdir("..");
echo "<br />ABS_PATH: ".ABS_PATH;
require_once(ABS_PATH."/functions/db_connect.php");

echo "<br />Host: ".db_host;
echo "<br />Database: ".db_name;
echo "<br />User: ".granted_db_user;
echo "<br />";

require_once("functions/class_db.php");

// $db=static_db::getInstance(db_host, db_name, granted_db_user, granted_db_pass);
$connection=db_connect(db_host, db_name, granted_db_user, granted_db_pass);

// chdir($dir_path);
require_once("config_serialized.php");

//Get creates for all tables
$sql="show tables;";
$create=array();
if($tt=mysql_query($sql))
{
	while($t=mysql_fetch_array($tt))
	{
		if($cc=mysql_query("show create table ".$t[0].";"))
		{
			if($c=mysql_fetch_assoc($cc))
			{
                if(isset($c['Create View']))
				{
                    $c['Create View']=str_replace("CREATE ALGORITHM", "\nCREATE ALGORITHM",$c['Create View']);
                    $c['Create View']=str_replace("`,`", "`,\n`",$c['Create View']); //Try and make lines shorter to avoid troubles with writing file
				}
				
				if(isset($c['Table']))
				{
					$c['Create Table']=str_replace("CREATE TABLE", "\nCREATE TABLE IF NOT EXISTS",$c['Create Table']);
					$c['Create Table']=str_replace("`".PREFIX, "`", $c['Create Table']);
					$c['Create Table']=preg_replace("/ AUTO_INCREMENT=\d*/","", $c['Create Table']);
					$c['Table']="`".$c['Table']."`";
					$c['Table']=str_replace("`".PREFIX, "`",$c['Table']);
					$c['Table']=str_replace("`", "",$c['Table']);
				}
				$create[]=$c;

				echo "c:<pre>".print_r($c,1)."</pre>";
			}
		}
	}
}

$sql="show triggers;";
echo "<pre>$sql</pre>";
if($tt=mysql_query($sql))
{
	echo mysql_affected_rows();
	while($t=mysql_fetch_array($tt))
	{
		$sql="show create trigger ".$t[0].";";
		echo "<pre>$sql</pre>";
		if($cc=mysql_query($sql))
		{
			if($c=mysql_fetch_assoc($cc))
			{
				if(isset($c['Trigger']))
				{
					$c['SQL Original Statement']=str_replace("CREATE DEFINER", "\nCREATE DEFINER", $c['SQL Original Statement']);
					$c['SQL Original Statement']=str_replace("ON `".PREFIX, "ON `" ,$c['SQL Original Statement']);
					$c['SQL Original Statement']=str_replace("INTO ".PREFIX, "INTO ", $c['SQL Original Statement']);
					$c['SQL Original Statement']=str_replace("TRIGGER `".PREFIX, "TRIGGER `", $c['SQL Original Statement']);
					$c['Trigger']="`".$c['Trigger']."`";
					$c['Trigger']=str_replace("`".PREFIX, "`",$c['Trigger']);
					$c['Trigger']=str_replace("`", "",$c['Trigger']);
				}
				$create[]=$c;

				echo "c:<pre>".print_r($c,1)."</pre>";
			}
		}
		else
			echo mysql_error();
	}
}
else
	echo mysql_error();

echo "CREATE:<pre>".print_r($create,1)."</pre>";

chdir($dir_path);
echo "Current dir: ".getcwd();
//Write all the create tables to a file
$to_write=serialize($create);
file_put_contents (SERIALIZED_PATH."/serialized_db.txt" , $to_write ); //write it outside of this folder so that it can be commited to right project
echo "<br />Wrote serialized to: ".SERIALIZED_PATH."/serialized_db.txt";
//Write all the create tables to a file json encoded
$to_write=json_encode($create);
file_put_contents (SERIALIZED_PATH."/json_db.txt" , $to_write ); //write it outside of this folder so that it can be commited to right project
echo "<br />Wrote json encoded to: ".SERIALIZED_PATH."/json_db.txt";
?>