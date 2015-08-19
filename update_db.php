<!DOCTYPE html>
<html lang="en" class="">
  <head prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
    <meta charset='utf-8'>
</head>
<body>
<?php
$dir_path=getcwd ();
chdir("../");
require_once("config.php"); 
require_once("functions/db_connect.php");
$connection=db_connect(db_host, db_name, db_user, db_pass);

chdir($dir_path);
require_once("config.php");

$serialized_db=file_get_contents ( SERIALIZED_PATH."/serialized_db.txt");
if($serialized_db!==FALSE)
{
	$create=unserialize($serialized_db);

	//First, just create the db's
	for($i=0; $i<count($create); $i++)
	{
		//create the table if it doesn't exist
		if(isset($create[$i]['Create Table']) && isset($create[$i]['Table']))
		{
			$create[$i]['Create Table']=str_replace("CREATE TABLE IF NOT EXISTS `".$create[$i]['Table']."`","CREATE TABLE IF NOT EXISTS `".PREFIX.$create[$i]['Table']."`",$create[$i]['Create Table']);
			if(!mysql_query($create[$i]['Create Table']))
			{
				echo "<pre>".$create[$i]['Create Table']."</pre>";
				echo "<pre>".mysql_error()."</pre>";
			}
		}
		else if(isset($create[$i]['Create View']))
		{
			$create[$i]['Create View']=str_replace("DEFINER=`root`@","DEFINER=`".db_user."`@",$create[$i]['Create View']);
			if(!mysql_query($create[$i]['Create View']))
			{
				echo "<pre>".$create[$i]['Create View']."</pre>";
				echo "<pre>".mysql_error()."</pre>";
			}
		}
		else
			echo "Not created:<pre>".print_r($create[$i],1)."</pre>";
	}
	
	echo "<p>Creation process complete</p>";
	
	//Now comes the tricky parts:
	//check that all columns exists
	//Check that all columns are the same types and stuff
	
	//Look for differences:
	$suggested_sql=array();
	for($i=0; $i<count($create); $i++)
	{
		if(isset($create[$i]['Table']))
		{
			echo "<h2>".PREFIX.$create[$i]['Table']."</h2>";
			
			$shell_rows=explode("\n",$create[$i]['Create Table']);
			
			if($cc=mysql_query("show create table ".PREFIX.$create[$i]['Table']))
			{
				if($c=mysql_fetch_assoc($cc))
				{
					$current_rows=explode("\n",$c['Create Table']);

					//Remove first row of both, because it only contains create table, wich can't be different.
					array_shift ( $shell_rows );
					array_shift ( $current_rows );
					
					//remove auto increment and trailing commas
					foreach($shell_rows as $key => $s)
					{
						$shell_rows[$key] = rtrim($s, ',');
						$shell_rows[$key]=preg_replace("/AUTO_INCREMENT=\d*/","", $shell_rows[$key]);
					}
					foreach($current_rows as $key => $s)
					{
						$current_rows[$key] = rtrim($s, ',');
						$current_rows[$key]=preg_replace("/AUTO_INCREMENT=\d*/","", $current_rows[$key]);
					}
					
					//sort shell_rows so that keys comes before the other stuff
					sort($shell_rows, SORT_STRING);

					echo "shell_rows<pre>".print_r($shell_rows,1)."</pre>";
					echo "current_rows<pre>".print_r($current_rows,1)."</pre>";
					
					//For each of $shell rows, check that the row exists in $current row
					foreach($shell_rows as $k => $s)
					{
						if(!in_array($s,$current_rows))
						{
							echo "<br />This exists in shell but not in current table: '$s'";
							
							if (strpos($s,'KEY') !== false)
							{
								$suggested_sql[]="ALTER TABLE ".PREFIX.$create[$i]['Table']." ADD ".$s.";";
							}
							else if(preg_match("/`[A-Za-z0-9_]*`/", $s, $matches)) //This should mean we are dealing with a column
							{
								//Check if $matches[0] exists in any of the rows in $current_rows
								$column_name = $matches[0];
								$alter=0;
								foreach($current_rows as $cr)
								{
									if(preg_match("/$column_name/", $cr))
										$alter=1;
								}
								if($alter)
								{
									//column exists in current table, so we should just alter it.
									$suggested_sql[]="ALTER TABLE ".PREFIX.$create[$i]['Table']." MODIFY ".$s.";";
								}
								else
								{
									//column DOES NOT exists in current table, so we should add it.
									$suggested_sql[]="ALTER TABLE ".PREFIX.$create[$i]['Table']." ADD ".$s.";";
								}
							}
							else if ($k==count($shell_rows)-1)
							{
								$s=str_replace(")", "", $s);
								$suggested_sql[]="ALTER TABLE ".PREFIX.$create[$i]['Table']." ".$s.";";
							}
							else 
								echo "<br />NO suggestion";
						}
					}
					
				}
			}
			else
				echo mysql_error();
		}
		else if(!isset($create[$i]['View']))
			echo "UNKNOWN:<pre>".print_r($create[$i],1)."</pre>";
			
	}
	
	echo "<h2>Suggested changes</h2>";
	foreach($suggested_sql as $s)
		echo "<br />$s";
	// echo "<pre>".print_r($suggested_sql,1)."</pre>";
}

db_close($connection);
?>
</body>
</html>