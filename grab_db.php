<?php
$dir_path=getcwd ();
chdir("../");
require_once("config.php"); 
require_once("functions/db_connect.php");
$connection=db_connect(db_host, db_name, db_user, db_pass);

chdir($dir_path);
require_once("config.php");

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

echo "CREATE:<pre>".print_r($create,1)."</pre>";

db_close($connection);

//Write all the create tables to a file
$to_write=serialize($create);
file_put_contents (SERIALIZED_PATH."/serialized_db.txt" , $to_write ); //write it outside of this folder so that it can be commited to right project
?>