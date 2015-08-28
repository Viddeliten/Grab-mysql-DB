<?php
$dir_path=getcwd ();
require_once("config_main.php");
chdir(MAIN_CONFIG_PATH);
require_once("config.php"); 
require_once("functions/db_connect.php");
$connection=db_connect(db_host, db_name, db_user, db_pass);

chdir($dir_path);
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
				if(isset($c['Table']))
				{
					$c['Create Table']=str_replace("CREATE TABLE", "CREATE TABLE IF NOT EXISTS",$c['Create Table']);
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
		//Otherwise, you can run your dump file through a script that would replace all occurrences of CREATE TABLE with CREATE TABLE IF NOT EXISTS.
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
					$c['SQL Original Statement']=str_replace("ON `".PREFIX, "ON `" ,$c['SQL Original Statement']);
					$c['SQL Original Statement']=str_replace("INTO ".PREFIX, "INTO ", $c['SQL Original Statement']);
					$c['SQL Original Statement']=str_replace("TRIGGER `".PREFIX, "TRIGGER `", $c['SQL Original Statement']);
					$c['Table']="`".$c['Table']."`";
					$c['Table']=str_replace("`".PREFIX, "`",$c['Table']);
					$c['Table']=str_replace("`", "",$c['Table']);
				}
				$create[]=$c;

				echo "c:<pre>".print_r($c,1)."</pre>";
			}
		}
		else
			echo mysql_error();
		//Otherwise, you can run your dump file through a script that would replace all occurrences of CREATE TABLE with CREATE TABLE IF NOT EXISTS.
	}
}
else
	echo mysql_error();

echo "CREATE:<pre>".print_r($create,1)."</pre>";

db_close($connection);

//Write all the create tables to a file
$to_write=serialize($create);
file_put_contents (SERIALIZED_PATH."/serialized_db.txt" , $to_write ); //write it outside of this folder so that it can be commited to right project
?>