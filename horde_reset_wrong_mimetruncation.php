<?php
/* 
 * horde_reset_wrong_mimetruncation - reset the MIME truncation settings for Outlook 2013
 * Copyright (C) 2014  Patrick De Zordo <patrick@spamreducer.eu>
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * -------------------------------------------------------------------------------------------------------------------------------------
 * 
 * Usage: php horde_reset_wrong_mimetruncation.php mysql_host horde_DB_name horde_DB_username horde_DB_password activesync_device_id
 * 
 * Example: php horde_reset_wrong_mimetruncation.php localhost horde_db horde_user horde_password 3278A0C997A24217BB1A105CC9569D12
 * 
 */
	
	$servername = $argv[ 1 ]; $horde_db = $argv[ 2 ]; $username = $argv[ 3 ]; $password = $argv[ 4 ]; $cache_devid = $argv[ 5 ];
	
	// Create connection
	$conn = mysqli_connect( $servername, $username, $password, $horde_db );
	// Check connection
	if ( !$conn ) { die( "Connection failed: " . mysqli_connect_error() ); }
	
	$sql = "SELECT cache_devid, cache_user, cache_data FROM horde_activesync_cache WHERE cache_devid like '" . $cache_devid . "'";
	$result = mysqli_query( $conn, $sql );
	
	if ( mysqli_num_rows( $result ) ) {
		$sql = "SELECT device_id, device_type, device_agent FROM horde_activesync_device WHERE device_id like '" . $cache_devid . "'";
		$inner_result = mysqli_query( $conn, $sql );
		if ( mysqli_num_rows( $inner_result ) ) $inner_row = mysqli_fetch_assoc( $inner_result );
		if ( $inner_row[ 'device_type' ] == 'WindowsOutlook15' ) {
			$row = mysqli_fetch_assoc( $result );
			echo "Processing ActiveSync device: '" . $row[ 'cache_devid' ] . "' | '" . $inner_row[ 'device_agent' ] . "' | '" . $row[ 'cache_user' ] . "'..\n";
			echo "Taking backup of old serialized values to local file: '" . $row[ 'cache_devid' ] . ".horde.bak'..\n";
			$backup_file = fopen( $row[ 'cache_devid' ] . ".horde_activesync_cache.bak", "w" ) or die( "Unable to open backup file! Exiting.." );
			if ( fwrite( $backup_file, $row[ 'cache_data' ] ) ) {
				$serialized_data = utf8_encode( $row[ 'cache_data' ] );
				$unserialized_data = unserialize( $serialized_data );
				$collections = $unserialized_data[ 'collections' ]; // filter out interesting part of array
				foreach ( $collections as $i => $value ) {
					/*if ( $collections[ $i ][ 'class' ] == 'Email' ) {*/
						echo "=> Repairing entry: '" . $collections[ $i ][ 'serverid' ] . "'\n";
						if ( @$collections[ $i ][ 'truncation' ] > 0 ) {
							unset( $collections[ $i ][ 'truncation' ] );
							echo "\t==> truncation='" . $collections[ $i ][ 'truncation' ] . "' --> parameter will be removed.\n";
						}
						if ( @$collections[ $i ][ 'mimetruncation' ] > false ) {
							$collections[ $i ][ 'mimetruncation' ] = false;
							echo "\t==> mimetruncation='" . $collections[ $i ][ 'mimetruncation' ] . "' --> parameter will be changed to 'false'.\n";
						}
					/*}*/
				}
				$unserialized_data[ 'collections' ] = $collections;
				$serialized_data = serialize( $unserialized_data );
				echo "-- Delete old 'horde_activesync_cache' DB entry.\n";
				$sql = "DELETE FROM horde_activesync_cache WHERE cache_devid like '" . $row[ 'cache_devid' ] . "'";
				if ( mysqli_query($conn, $sql) ) {
					echo "++ Insert new 'horde_activesync_cache' DB entry.\n";
					$sql = "INSERT INTO horde_activesync_cache (cache_devid, cache_user, cache_data) VALUES ('" . $row[ 'cache_devid' ] . "', '" . $row[ 'cache_user' ] . "', '" . utf8_decode( $serialized_data ) . "')";
					if ( !mysqli_query($conn, $sql) ) echo "Error: " . mysqli_error($conn);
				} else echo "Error: " . mysqli_error($conn);
			} else echo "Unable to write to backup file! Exiting..";
			fclose($backup_file);
		} else print( "The specified device is not known to have problems with this bug! Exiting.." );
	} else echo "No such ActiveSync device-ID found! Exiting..";
	echo("\n");
	mysqli_close($conn);
			
?>
