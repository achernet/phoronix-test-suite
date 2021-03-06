<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2010 - 2015, Phoronix Media
	Copyright (C) 2010 - 2015, Michael Larabel

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

class pts_result_file_output
{
	public static function result_file_to_json(&$result_file)
	{
		$json = array();
		$json['title'] = $result_file->get_title();

		$json['results'] = array();
		foreach($result_file->get_result_objects() as $result_object)
		{
			$json['results'][$result_object->test_profile->get_identifier()] = array(
				'arguments' => $result_object->get_arguments_description(),
				'units' => $result_object->test_profile->get_result_scale(),
				);

			foreach($result_object->test_result_buffer as &$buffers)
			{
				foreach($buffers as &$buffer)
				{
					$json['results'][$result_object->test_profile->get_identifier()]['results'][$buffer->get_result_identifier()] = array(
						'value' => $buffer->get_result_value()
						);
				}
			}
		}

		return json_encode($json, JSON_PRETTY_PRINT);
	}
	public static function result_file_to_csv(&$result_file)
	{
		$csv_output = null;
		$delimiter = ',';

		$csv_output .= $result_file->get_title() . PHP_EOL . PHP_EOL;

		$columns = $result_file->get_system_identifiers();
		$rows = array();
		$table_data = array();

		pts_result_file_analyzer::system_components_to_table($table_data, $columns, $rows, $result_file->get_system_hardware());
		pts_result_file_analyzer::system_components_to_table($table_data, $columns, $rows, $result_file->get_system_software());

		$csv_output .= ' ';

		foreach($columns as $column)
		{
			$csv_output .= $delimiter . '"' . $column . '"';
		}
		$csv_output .= PHP_EOL;

		foreach($rows as $i => $row)
		{
			$csv_output .= $row;

			foreach($columns as $column)
			{
				$csv_output .= $delimiter . $table_data[$column][$i];
			}

			$csv_output .= PHP_EOL;
		}

		$csv_output .= PHP_EOL;
		$csv_output .= ' ';

		foreach($columns as $column)
		{
			$csv_output .= $delimiter . '"' . $column . '"';
		}
		$csv_output .= PHP_EOL;

		foreach($result_file->get_result_objects() as $result_object)
		{
			$csv_output .= '"' . $result_object->test_profile->get_title() . ' - ' . $result_object->get_arguments_description() . '"';

			foreach($result_object->test_result_buffer->get_values() as $value)
			{
				$csv_output .= $delimiter . $value;
			}
			$csv_output .= PHP_EOL;
		}
		$csv_output .= PHP_EOL;

		return $csv_output;
	}
	public static function result_file_to_text(&$result_file, $terminal_width = 80)
	{
		$result_output = null;

		$result_output .= $result_file->get_title() . PHP_EOL;
		$result_output .= $result_file->get_description() . PHP_EOL . PHP_EOL . PHP_EOL;

		$system_identifiers = $result_file->get_system_identifiers();
		$system_hardware = $result_file->get_system_hardware();
		$system_software = $result_file->get_system_software();

		for($i = 0; $i < count($system_identifiers); $i++)
		{
			$result_output .= $system_identifiers[$i] . ': ' . PHP_EOL . PHP_EOL;
			$result_output .= "\t" . $system_hardware[$i] . PHP_EOL . PHP_EOL . "\t" . $system_software[$i] . PHP_EOL . PHP_EOL;
		}

		$longest_identifier_length = $result_file->get_system_identifiers();
		$longest_identifier_length = strlen(pts_strings::find_longest_string($longest_identifier_length)) + 2;

		foreach($result_file->get_result_objects() as $result_object)
		{
			$result_output .= trim($result_object->test_profile->get_title() . ' ' . $result_object->test_profile->get_app_version() . PHP_EOL . $result_object->get_arguments_description());

			if($result_object->test_profile->get_result_scale() != null)
			{
				$result_output .= PHP_EOL . '  ' .  $result_object->test_profile->get_result_scale();
			}

			foreach($result_object->test_result_buffer as &$buffers)
			{
				$max_value = 0;
				$min_value = pts_arrays::first_element($buffers)->get_result_value();
				foreach($buffers as &$buffer_item)
				{
					if($buffer_item->get_result_value() > $max_value)
					{
						$max_value = $buffer_item->get_result_value();
					}
					else if($buffer_item->get_result_value() < $min_value)
					{
						$min_value = $buffer_item->get_result_value();
					}
				}

				$longest_result = strlen($max_value) + 1;
				foreach($buffers as &$buffer_item)
				{
					$val = $buffer_item->get_result_value();

					if(stripos($val, ',') !== false)
					{
						$vals = explode(',', $val);
						$val = 'MIN: ' . min($vals) . ' / AVG: ' . round(array_sum($vals) / count($vals), 2) . ' / MAX: ' . max($vals);
					}

					$result_output .= PHP_EOL . '    ' . $buffer_item->get_result_identifier() . ' ';

					$result_length_offset = $longest_identifier_length - strlen($buffer_item->get_result_identifier());
					if($result_length_offset > 0)
					{
						$result_output .= str_repeat('.', $result_length_offset) . ' ';
					}
					$result_output .= $val;


					if(is_numeric($val))
					{
						$result_output .= str_repeat(' ', $longest_result - strlen($val))  . '|';
						$current_line_length = strlen(substr($result_output, strrpos($result_output, PHP_EOL) + 1)) + 1;
						$result_output .= str_repeat('=', round(($val / $max_value) * ($terminal_width - $current_line_length)));

					}
				}
			}

			$result_output .= PHP_EOL . PHP_EOL;
		}

		return $result_output;
	}
}

?>
