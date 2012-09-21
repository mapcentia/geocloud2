<div>
<div id='searchbutton' style='height:20'></div>
<script>
<!--
var searchButtonActive="<b style='cursor:pointer;text-decoration:underline;color:red;' onClick='spatialsearch();'><? echo $languageText[search2]; ?></b>";
var searchButtonInactive="";
document.getElementById("searchbutton").innerHTML = searchButtonInactive;
function spatialsearch()
{
document.theForm.search.value="true";
update();
}
function showsearch()
{
document.getElementById("searchbutton").innerHTML = searchButtonActive;
}
-->
</script>
<select class='selectbox' name="query_mode" onChange="update();">
<option>Search:</option>
<option>- - - - - - - - - - - - -</option>
<option value="airports" <?php if ($query_mode=="airports") echo "SELECTED";?>>Airports</option>
<option value="mcd90py2" <?php if ($query_mode=="mcd90py2") echo "SELECTED";?>>City or town</option>
</select><br>
<!-- Select form for airport query -->
<?
 if (
	$query_mode == "airports")
{
	echo "<SELECT class='selectbox' name='airportName' onchange='showsearch();spatialsearch();'>";
	$query = ("select * from airports order by name");
	$result = pg_exec($query);
	$num_results = pg_numrows($result);
	if (!$airportName)
	{
		echo "<option SELECTED;>Name</option>";
		echo "<option>- - - - - - -</option>";
	} else
	{
		echo "<option value='$airportName' SELECTED;>$airportName</option>";
		echo "<script>showsearch();</script>";
	}
	for ($i = 0; $i < $num_results; $i ++)
	{
		$row = pg_fetch_array($result);
		echo "<option value='$row[name]'>$row[name]</option>";
	}
	echo "</SELECT>";
	
	
	$query =
		("select * from airports where name='$airportName'");
	$result = pg_exec($query);
	$row = pg_fetch_array($result);
	
	echo "<input type='hidden' name='mode' value='airports'>";
	
	
}
?> 

<!-- Select form for city or town query -->
<?
 if (
	$query_mode == "mcd90py2")
{
	echo "<SELECT class='selectbox' name='city_name' onchange='showsearch();spatialsearch();;'>";
	$query = ("select * from mcd90py2 order by city_name");
	$result = pg_exec($query);
	$num_results = pg_numrows($result);
	if (!$city_name)
	{
		echo "<option SELECTED;>City name</option>";
		echo "<option>- - - - - - -</option>";
		
	} else
	{
		echo "<option value='$city_name' SELECTED;>$city_name</option>";
		echo "<script>showsearch();</script>";
	}
	for ($i = 0; $i < $num_results; $i ++)
	{
		$row = pg_fetch_array($result);
		echo "<option value='$row[city_name]'>$row[city_name]</option>";
	}
	echo "</SELECT>";
	$query =
		("select * from mcd90py2 where city_name='$city_name'");
	$result = pg_exec($query);
	$row = pg_fetch_array($result);
	echo "<input type='hidden' name='mode' value='mcd90py2'>";
	
}
?> 
</div> 
