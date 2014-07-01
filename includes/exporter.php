<?php


$file	=	'export';

// You can change this to a non-live settings table...
$settingsTable = "wp_connections_export_settings";

// Open the database, get the export settings, and set up the export fields...
openExport();

// Draw the header (sets up field breakouts, so it must be called if you use breakouts)...
exportHeader();

// Draw the main export data based on settings acquired in the above 2 functions...
exportCells();

// Close the export...
closeExport();

// Connects to the database, loads export settings, defines export delimiters and begins the export process...
function openExport() {
	
/*
			define( 'CN_ENTRY_TABLE', $prefix . 'connections' );
			define( 'CN_ENTRY_ADDRESS_TABLE', $prefix . 'connections_address' );
			define( 'CN_ENTRY_PHONE_TABLE', $prefix . 'connections_phone' );
			define( 'CN_ENTRY_EMAIL_TABLE', $prefix . 'connections_email' );
			define( 'CN_ENTRY_MESSENGER_TABLE', $prefix . 'connections_messenger' );
			define( 'CN_ENTRY_SOCIAL_TABLE', $prefix . 'connections_social' );
			define( 'CN_ENTRY_LINK_TABLE', $prefix . 'connections_link' );
			define( 'CN_ENTRY_DATE_TABLE', $prefix . 'connections_date' );

			define( 'CN_ENTRY_TABLE_META', $prefix . 'connections_meta' );
			define( 'CN_TERMS_TABLE', $prefix . 'connections_terms' );
			define( 'CN_TERM_TAXONOMY_TABLE', $prefix . 'connections_term_taxonomy' );
			define( 'CN_TERM_RELATIONSHIP_TABLE', $prefix . 'connections_term_relationships' );
			*/	
	
	global	$wpdb, $exportData, $contacts, $exportFields, $settings, $settingsTable, $numContacts;

	$contacts = $wpdb->get_results( "SELECT * FROM ".CN_ENTRY_TABLE, ARRAY_A );
	$numContacts = count($contacts);




	$exportFields=$wpdb->get_results(
		'SELECT * FROM '.$settingsTable.' WHERE type > -1 ORDER BY field_order',
		ARRAY_A
	);
	
	
	
	
	
	
/*


	$i=$wpdb->get_results(
		'SELECT * FROM '.$settingsTable.' WHERE type = -1',
		ARRAY_A
	);
	parse_str($i['fields'], $settings);*/
	
	$settings['exportType']="csv";
	
	

	switch (strtolower($settings['exportType'])) {
		case "htm":
		case "html":
			$settings['outputOpenData']		= "<table border=1>\r\n";
			$settings['outputOpenHeader']	= "\t<tr>\r\n";
			$settings['outputCloseHeader']	= "\t</tr>\r\n";
			$settings['outputOpenRec']		= "\t<tr>\r\n";
			$settings['outputOpenDelim']	= "\t\t<td>";
			$settings['outputCloseDelim']	= "</td>\r\n";
			$settings['outputCloseRec']		= "\t</tr>\r\n";
			$settings['outputCloseData']	= "</table>\r\n";
			$settings['escape']				= '&';
			$settings['escapeWith']			= '&amp;';
			break;
		case "tsv":
			$settings['outputOpenData']		= "";
			$settings['outputOpenHeader']	= "";
			$settings['outputCloseHeader']	= "\r\n";
			$settings['outputOpenRec']		= "";
			$settings['outputOpenDelim']	= "";
			$settings['outputCloseDelim']	= "\t";
			$settings['outputCloseRec']		= "\r\n";
			$settings['outputCloseData']	= "";
			$settings['escape']				= "\t";
			$settings['escapeWith']			= "\t\t";
			break;
		case "csv":
			$settings['outputOpenData']		= "";
			$settings['outputOpenHeader']	= "";
			$settings['outputCloseHeader']	= "\r\n";
			$settings['outputOpenRec']		= "";
			$settings['outputOpenDelim']	= '"';
			$settings['outputCloseDelim']	= '",';
			$settings['outputCloseRec']		= "\r\n";
			$settings['outputCloseData']	= "";
			$settings['escape']				= '"';
			$settings['escapeWith']			= '""';
			break;
	}


	// Write the open data code to the export to get things started...
	$exportData .= $settings['outputOpenData'];
}
function isJson($string) {
 json_decode($string);
 return (json_last_error() == JSON_ERROR_NONE);
}
// Writes out the data fields...
function exportCells() {
	global $settings,$contacts, $exportData, $exportFields, $maxCategories, $wpdb;
	
	
	$dataset = '';
	// Go through each contact...
	foreach($contacts as $contact) {
		
		
		$meta = $wpdb->get_results( "SELECT * FROM ".CN_ENTRY_TABLE_META." WHERE `entry_id`=".$contact['id'], ARRAY_A );
		
		
		foreach($meta as $part){
			$key=$part['meta_key'];
			$value=$part['meta_value'];
			if(isJson($value)){
				$json_value = json_decode($value);
				foreach($json_value as $jkey=>$jpart){
					$true_value=($jpart=='0')?'false':($jpart=='1')?'true':$jpart;
					$contact[$key."::".$jkey]=$true_value;	
				}
			}else{
				$contact[$key]=$value;	
			}
		}
		$rec = '';
		foreach($contact as $key=>$part){
			$value=part_filter(c,$part);
			$rec.=$settings['outputOpenDelim'].$part.$settings['outputCloseDelim'];
		}
		$dataset .= exportRecord($rec);
	}
	// Now write the data...
	$exportData .= $dataset;
	
}

function part_filter($key,$part){
	
	switch (strtolower($key)) {
		case "date_added":
			$part=date('l jS \of F Y h:i:s A',$part);
			break;
	}
	
	
	
	return $part;
}

// Writes out the header to the $exportData string...
function exportHeader() {
	global $exportData, $exportFields, $settings, $wpdb;
	$header = '';
	// Now write the header...
	$exportData .= $settings['outputOpenHeader'] . $header . $settings['outputCloseHeader'];
}

// This is called for each breakout field encountered while writing the header, it returns all header cells that needed to be drawn by the breakout.
// It also populates the fields and types array strings if they're empty.
function explodeBreakoutHeader(&$breakout) {
	global $db, $maxCategories,$wpdb;

	// We need a list of fields (i.e. adr_line1, adr_line2, city, state, zip), and a list of types (i.e. work, home, other)

	// If 'table_name' doesn't exist, put the contents of 'field' into it (this step allows for odd things like the dates field/date table)...
	if (empty($breakout['table_name'])) $breakout['table_name'] = $breakout['field'];

	// Get an array of each field we need to use...
	$breakoutFields = explode(";", $breakout['fields']);

	// If no breakout field list was specified, include all fields...
	if (empty($breakout['fields'])) {
		// Get the field names from the SQL schema for the table we're going to use, and plop them into an array...
		$result = $wpdb->get_col("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = '".$db."' AND table_name = 'wp_connections_".$breakout['table_name']."';");
		$i=0;
		foreach ($result as $value) {
			$breakoutFields[$i] = $value[0];
		}
		// Copy the array back into the fields item for use later...
		$breakout['fields'] = implode(";", $breakoutFields);
	}

	// ### Take care of the types...

	// Get an array of each type we need to use...
	$breakoutTypes = explode(";", $breakout['breakout_types']);
	// You can specify you only want home addresses in an export for example, if nothing is specified, get a list of all types from the breakout's table...
	if (empty($breakout['breakout_types'])) {
		// Put the result into an array...
		$result = $wpdb->get_col("SELECT DISTINCT type FROM wp_connections_".$breakout['table_name']." ORDER BY type");
		// Put a list of types for this breakout into an array...
		$i=0;
		foreach ($result as $value) {
			$breakoutTypes[$i] = $value['type'];
		}
		// Copy the array back into the breakout_types item for use later...
		$breakout['breakout_types'] = implode(";", $breakoutTypes);
	}

	// ### When we get here, both the fields and types are checked and ready to go in 2 arrays, we just need to write the breakout...

	// Handle different types...
	switch ($breakout['type']) {
		// Explode all field columns and types...
		case 1:
			foreach ($breakoutTypes as $type) {
				foreach ($breakoutFields as $field) {
					$line .= exportBreakoutHeaderField($breakout, $field, $type);
				}
			}
			break;
		// Joined from another table
		case 2:
			$line .= data($breakout['display_as']);
			break;
		// Breakout a list in the header...
		case 3:
			$rTemp = $wpdb->get_col("SELECT id FROM wp_connections");
			// Go through each contact...
			$maxCategories = 0;
			foreach($rTemp as $value){
				// And get a count of how many categories it has...
				$rTemp2 = $wpdb->get_var("SELECT count(*) FROM wp_connections_terms JOIN wp_connections_term_relationships ON wp_connections_term_relationships.term_taxonomy_id = wp_connections_terms.term_id WHERE wp_connections_term_relationships.entry_id = ".$value['id']);
				// Find the biggest result...
				if ($rTemp2 > $maxCategories) $maxCategories = $rTemp2;
			}

			// Finally, write a list of fields for each category...
			for ($i = 1; $i < $maxCategories+1; $i++) {
				$line .= data('Category '.$i);
			}
			break;
		// Breakout a list in the header, using primaries in the first column...
		case 4:
			$rTemp = $wpdb->get_col("SELECT id FROM wp_connections");
			// Go through each contact...
			$maxCategories = 0;
			foreach($rTemp as $value){
				// And get a count of how many categories it has...
				$rTemp2 = $wpdb->get_var("SELECT count(*) FROM wp_connections_terms JOIN wp_connections_term_relationships ON wp_connections_term_relationships.term_taxonomy_id = wp_connections_terms.term_id WHERE wp_connections_term_relationships.entry_id = ".$value['id']);
				$res = mysql_fetch_array($rTemp2);
				// Find the biggest result...
				if ($rTemp2 > $maxCategories) $maxCategories = $rTemp2;
			}

			// Finally, write a list of fields for each category...
			for ($i = 0; $i < $maxCategories+1; $i++) {
				if ($i == 0) {
					$line .= data('Main Category');
				} else {
					$line .= data('Sub-Cat '.$i);
				}
			}
			break;
	}

	return $line;
}

// outputs a breakout header type...
function exportBreakoutHeaderField($breakout, $field, $type) {
	global $settings;
	if ($settings['ucfirst'] == 1) {
		$field = ucfirst($field);
		$type = ucfirst($type);
	}
	// Display the field name based on settings...
	switch (strtolower($breakout['display'])) {
		case 'pre':
			return data($type .' '. $field);
		case 'post':
			return data($field .' '. $type);
		case 'only':
			return data($type);
		default:
			return data($field);
	}
}

// Writes out an entire record to the $exportData string...
function exportRecord($data) {
	global $settings;
	return $settings['outputOpenRec'] . $data . $settings['outputCloseRec'];
}

// Returns an export-ready data item...
function data($data) {
	global $settings;
	$data = str_replace($settings['escape'], $settings['escapeWith'], $data);
	$data = str_replace('&amp;', '&', $data);
	$data = str_replace('&nbsp;', ' ', $data);
	return $settings['outputOpenDelim'] . $data . $settings['outputCloseDelim'];
}

function closeExport() {
	global $settings, $exportData, $file;
	$file = 'export';
	// Write the close statement to the export data stream...
	$exportData .= $settings['outputCloseData'];
	// Process special instructions for closing the export...
	switch (strtolower($settings['exportType'])) {
		case 'htm':
		case 'html':
			// Echo the data...
			echo $exportData;
		case 'tsv':
			$filename = $file."_".date("Y-m-d_H-i",time());
			header("Content-type: application/vnd.ms-excel");
			header("Content-disposition: tsv" . date("Y-m-d") . ".tsv");
			header( "Content-disposition: filename=".$filename.".tsv");
			print $exportData;
		case 'csv':
			$filename = "export_".date("Y-m-d_H-i",time());
			header("Content-type: application/vnd.ms-excel");
			header("Content-disposition: csv" . date("Y-m-d") . ".csv");
			header( "Content-disposition: filename=".$filename.".csv");
			print $exportData;
	}
}
?>
