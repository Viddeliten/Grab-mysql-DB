<!DOCTYPE html>
<html lang="en" class="">
  <head>
    <meta charset='utf-8'>
</head>
<body>
<?php
$dir_path=getcwd ();
require_once("config_main.php");
chdir(MAIN_CONFIG_PATH);
require_once("config.php"); 
require_once("functions/class_db.php");
require_once("functions/db_connect.php");
require_once("functions/string.php");
// $connection=db_connect(db_host, db_name, granted_db_user, granted_db_pass);
$db=new db_class(db_host, db_name, granted_db_user, granted_db_pass);

echo "<br />Host: ".db_host;
echo "<br />Database: ".db_name;
echo "<br />User: ".granted_db_user;
echo "<br />";

chdir($dir_path);
require_once("config_serialized.php");

$suggested_sql=array();

if(file_exists(SERIALIZED_PATH."/json_db.txt"))
{
	$json_db=file_get_contents ( SERIALIZED_PATH."/json_db.txt");
	$create=json_decode($json_db, TRUE);
}
else
{
    echo "<br /> File: ".SERIALIZED_PATH."/serialized_db.txt";
	$serialized_db=file_get_contents ( SERIALIZED_PATH."/serialized_db.txt");
	$create=unserialize($serialized_db);
}

$tables=array();
$view_creates=array();

if(isset($create) && $create!==FALSE)
{
    // preprint($create, "<br />DEBUG0903");
    
	//First, just create the db's
	for($i=0; $i<count($create); $i++)
	{
		//create the table if it doesn't exist
		if(isset($create[$i]['Create Table']) && isset($create[$i]['Table']))
		{
            $tables[]=$create[$i]['Table'];
            
			$create[$i]['Create Table']=str_replace("\nCREATE TABLE IF NOT EXISTS `".$create[$i]['Table']."`","CREATE TABLE IF NOT EXISTS `".PREFIX.$create[$i]['Table']."`",$create[$i]['Create Table']);
			$create[$i]['Create Table']=preg_replace("/ AUTO_INCREMENT=\d*/","", $create[$i]['Create Table']);
			if(!$db->query($create[$i]['Create Table']))
			{
				echo "<br />48 - Create Table:<pre>".$create[$i]['Create Table']."</pre>";
				echo "<pre>".$db->error."</pre>";
				$suggested_sql[]=$create[$i]['Create Table'].";";
			}
			// else
				// echo "<br /><pre>".$create[$i]['Create Table']."</pre>";
		}
		else if(isset($create[$i]['Create View']))
		{
			// Change the defining user to the user we are logged in with now
			$create[$i]['Create View']=preg_replace("/DEFINER=`([^`]+)`@/","DEFINER=`".granted_db_user."`@", $create[$i]['Create View']);
            $create[$i]['Create View']=preg_replace("/".db_name."./","/".db_name.".".PREFIX."/", $create[$i]['Create View']);
			$create[$i]['Create View']=str_replace("\nCREATE ALGORITHM","CREATE OR REPLACE ALGORITHM",$create[$i]['Create View']);
			$create[$i]['Create View']=str_replace("CREATE ALGORITHM","CREATE OR REPLACE ALGORITHM",$create[$i]['Create View']);
			// $create[$i]['Create View']=preg_replace("/join `([^`]+)` /","join `".PREFIX."\\1` ",$create[$i]['Create View']);
			// $create[$i]['Create View']=preg_replace("/select `([^`]+)`.`([^`]+)` /","select `".PREFIX."\\1`.`\\2` ",$create[$i]['Create View']);
			$create[$i]['Create View']=preg_replace("/from `((?!".PREFIX.")[^`]+)`([^\.])/","from `".PREFIX."\\1`\\2",$create[$i]['Create View']);
			$create[$i]['Create View']=preg_replace("/\=[\s]*\`([^`]+)\`\.\`([^`]+)\` /","= `".PREFIX."\\1`.`\\2` ",$create[$i]['Create View']);
			// $create[$i]['Create View']=preg_replace("/VIEW \`([^`]+)\` AS/","VIEW `".PREFIX."\\1` AS",$create[$i]['Create View']);
			
			preg_match_all("/\`((?!".PREFIX.")[^`]+)\`\.\`([^`]+)\`/",$create[$i]['Create View'],$matches);
			// preprint($matches, "DEBUG1333 matches");
			foreach($matches[1] as $key => $word)
			{
				// If the word is a table or view, we must add the prefix
				$db->query("SELECT 1 FROM `".PREFIX.$word."` LIMIT 1;");
				if($db->error==NULL)
				{
					preprint(PREFIX.$word,"table- ".$key.";");
					$pattern="/\`".$word."\`\.\`([^`]+)\`/";
					$create[$i]['Create View']=preg_replace($pattern,"`".PREFIX.$word."`.`\\1` ",$create[$i]['Create View']);
				}
			}
// join `table`
			preg_match_all("/join \`((?!".PREFIX.")[^`]+)\`/",$create[$i]['Create View'],$matches);
			// preprint($matches, "DEBUG1334 matches");
			foreach($matches[1] as $key => $word)
			{
				// If the word is a table or view, we must add the prefix
				$db->query("SELECT 1 FROM `".PREFIX.$word."` LIMIT 1;");
				if($db->error==NULL)
				{
					$pattern="/join \`".$word."\`/";
					$create[$i]['Create View']=preg_replace($pattern,"join `".PREFIX.$word."`",$create[$i]['Create View']);
				}
			}

            $view_creates[]=$create[$i]['Create View'];
		}
		else if(isset($create[$i]['SQL Original Statement']))
		{
			$sql="DROP TRIGGER IF EXISTS ".PREFIX.$create[$i]['Trigger'];

            echo "<br />$sql</pre>";
			if(!$db->query($sql))
			{
				echo "<pre>".$db->error."</pre>";
			}

			// $create[$i]['SQL Original Statement']=preg_replace("/DEFINER=`[A-Za-z0-9_-]*`@/","DEFINER=`root`@", $create[$i]['SQL Original Statement']);
			$create[$i]['SQL Original Statement']=preg_replace("/DEFINER=`[^\s]*`/","", $create[$i]['SQL Original Statement']); // run it without definer
			$create[$i]['SQL Original Statement']=str_replace("INSERT INTO ","INSERT INTO ".PREFIX,$create[$i]['SQL Original Statement']);
			$create[$i]['SQL Original Statement']=str_replace("ON `","ON `".PREFIX,$create[$i]['SQL Original Statement']);
			$create[$i]['SQL Original Statement']=str_replace("TRIGGER `","TRIGGER `".PREFIX,$create[$i]['SQL Original Statement']);
			// $create[$i]['SQL Original Statement']=str_replace("INSERT INTO ","INSERT INTO ".PREFIX,$create[$i]['SQL Original Statement']);
			
            echo "<br />SQL Original Statement:<pre>".$create[$i]['SQL Original Statement']."</pre>";
			if(!$db->query($create[$i]['SQL Original Statement']))
			{
				echo "<pre>".$db->error.print_r($create[$i],1)."</pre>";
				$suggested_sql[]=$create[$i]['SQL Original Statement'].";";
			}
		}
		else
			echo "Not created:<pre>".print_r($create[$i],1)."</pre>";
	}
    
    //Create views
    if(!empty($view_creates))
    {
        foreach($view_creates as $view)
        {
            $create_view=$view;
            foreach($tables as $table)
            {
                // echo "<br />preg_replace(\"/([^.])`".$table."`\./","\\0`".PREFIX.$table."`.\", $create);";
                $create_view=preg_replace("/([^\.\"AS \"])`".$table."`/","\\1`".PREFIX.$table."`", $create_view);
            }
            echo "<br />Create View:<pre>".$create_view."</pre>";
            if(!$db->query($create_view))
            {
                echo "<pre>".$db->error."</pre>";
                $suggested_sql[]=$create_view.";";
            }
        }
    }
	
	echo "<p>Creation process complete</p>";
	
	//Now comes the tricky parts:
	//check that all columns exists
	//Check that all columns are the same types and stuff
	
	//Look for differences:
    
	for($i=0; $i<count($create); $i++)
	{
		if(isset($create[$i]['Create Table']))
		{
			echo "<h2>".PREFIX.$create[$i]['Table']."</h2>";
			
			$source_rows=explode("\n",$create[$i]['Create Table']);
			
			if($c=$db->select_first("show create table ".PREFIX.$create[$i]['Table']))
			{
                $current_rows=explode("\n",$c['Create Table']);

                //Remove first row of both, because it only contains create table, wich can't be different.
                array_shift ( $source_rows );
                array_shift ( $current_rows );
                
                //remove auto increment and trailing commas
                foreach($source_rows as $key => $s)
                {
                    $source_rows[$key] = rtrim($s, ',');
                    $source_rows[$key]=preg_replace("/ AUTO_INCREMENT=\d*/","", $source_rows[$key]);
                }
                foreach($current_rows as $key => $s)
                {
                    $current_rows[$key] = rtrim($s, ',');
                    $current_rows[$key]=preg_replace("/ AUTO_INCREMENT=\d*/","", $current_rows[$key]);
                }
			}
			else
				echo $db->error;

			//sort source_rows so that keys comes before the other stuff
			sort($source_rows, SORT_STRING);

			echo "source_rows<pre>".print_r($source_rows,1)."</pre>";
			echo "current_rows<pre>".print_r($current_rows,1)."</pre>";
			
			//For each of $shell rows, check that the row exists in $current row
			foreach($source_rows as $k => $s)
			{
				//It might be a constraint, and it might reference a table that needs a prefix.
				$s=preg_replace("/REFERENCES `([a-z0-9_]*)`/","REFERENCES `".PREFIX."\\1`", $s);

				$sql="";
				if(!in_array($s,$current_rows))
				{
					echo "<br />This exists in shell but not in current table: '$s'";
					
					if (strpos($s,'KEY') !== false)
					{
                        $sql="ALTER TABLE ".PREFIX.$create[$i]['Table']." ADD ".$s.";";
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
							$sql="ALTER TABLE ".PREFIX.$create[$i]['Table']." MODIFY ".$s.";";
						}
						else
						{
							//column DOES NOT exists in current table, so we should add it.
							$sql="ALTER TABLE ".PREFIX.$create[$i]['Table']." ADD ".$s.";";
						}
					}
					else if ($k==count($source_rows)-1)
					{
						$s=str_replace(")", "", $s);
						$sql="ALTER TABLE ".PREFIX.$create[$i]['Table']." ".$s.";";
					}
					else 
						echo "<br />NO suggestion";
					
                    if(!$db->query($sql))
					{
						echo "<br />Create Table:<pre>".$sql."</pre>";
						echo "<pre>".$db->error."</pre>";
						$suggested_sql[]=$sql;
					}
				}
			}
		}
		else if(!isset($create[$i]['View']) && !isset($create[$i]['Trigger']))
			echo "UNKNOWN:<pre>".print_r($create[$i],1)."</pre>";
			
	}
	
	echo "<h2>Suggested changes</h2>";
	foreach($suggested_sql as $s)
		echo "<br />$s";
	// echo "<pre>".print_r($suggested_sql,1)."</pre>";
}
?>
</body>
</html>