<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2008 - 2015, Phoronix Media
	Copyright (C) 2008 - 2015, Michael Larabel
	pts_module_interface.php: The generic Phoronix Test Suite module object that is extended by the specific modules/plug-ins

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

class pts_module
{
	const MODULE_UNLOAD = "MODULE_UNLOAD";
	const QUIT_PTS_CLIENT = "QUIT_PTS_CLIENT";

	public static function save_dir()
	{
		$prefix_dir = PTS_MODULE_DATA_PATH;
		pts_file_io::mkdir($prefix_dir);

		return $prefix_dir . str_replace('_', '-', self::module_name()) . '/';
	}
	public static function is_module($name)
	{
		return is_file(PTS_MODULE_LOCAL_PATH . $name . ".php") || is_file(PTS_MODULE_PATH . $name . ".php");
	}
	public static function module_config_save($module_name, $set_options = null)
	{
		// Validate the config files, update them (or write them) if needed, and other configuration file tasks
		pts_file_io::mkdir(PTS_MODULE_DATA_PATH . $module_name);
		$settings_to_write = array();

		if(is_file(PTS_MODULE_DATA_PATH . $module_name . "/module-settings.xml"))
		{
			$module_config_parser = new nye_XmlReader(PTS_MODULE_DATA_PATH . $module_name . "/module-settings.xml");
			$option_identifier = $module_config_parser->getXMLArrayValues('PhoronixTestSuite/ModuleSettings/Option/Identifier');
			$option_value = $module_config_parser->getXMLArrayValues('PhoronixTestSuite/ModuleSettings/Option/Value');

			for($i = 0; $i < count($option_identifier); $i++)
			{
				$settings_to_write[$option_identifier[$i]] = $option_value[$i];
			}
		}

		foreach($set_options as $identifier => $value)
		{
			$settings_to_write[$identifier] = $value;
		}

		$config = new nye_XmlWriter();

		foreach($settings_to_write as $identifier => $value)
		{
			$config->addXmlNode('PhoronixTestSuite/ModuleSettings/Option/Identifier', $identifier);
			$config->addXmlNode('PhoronixTestSuite/ModuleSettings/Option/Value', $value);
		}

		$config->saveXMLFile(PTS_MODULE_DATA_PATH . $module_name . "/module-settings.xml");
	}
	public static function is_module_setup()
	{
		$module_name = self::module_name();
		$is_setup = true;

		$module_setup_options = pts_module_manager::module_call($module_name, "module_setup");

		foreach($module_setup_options as $option)
		{
			if($option instanceOf pts_module_option)
			{
				if(pts_module::read_option($option->get_identifier()) == false && $option->setup_check_needed())
				{
					$is_setup = false;
					break;
				}
			}
		}

		return $is_setup;
	}
	public static function read_variable($var)
	{
		// For now this is just readung from the real env
		return trim(getenv($var));
	}
	public static function valid_run_command($module, $command = null)
	{
		if($command == null)
		{
			if(strpos($module, '.') != false)
			{
				list($module, $command) = explode('.', $module);
			}
			else
			{
				$command = 'run';
			}
		}

		if(!pts_module_manager::is_module_attached($module))
		{
			pts_module_manager::attach_module($module);
		}

		$all_options = pts_module_manager::module_call($module, 'user_commands');
		$valid = count($all_options) > 0 && ((isset($all_options[$command]) && method_exists($module, $all_options[$command])) || !empty($all_options));

		return $valid ? array($module, $command) : false;
	}
	public static function read_option($identifier, $default_fallback = false)
	{
		$module_name = self::module_name();
		$value = false;

		$module_config_parser = new nye_XmlReader(PTS_MODULE_DATA_PATH . $module_name . "/module-settings.xml");
		$option_identifier = $module_config_parser->getXMLArrayValues('PhoronixTestSuite/ModuleSettings/Option/Identifier');
		$option_value = $module_config_parser->getXMLArrayValues('PhoronixTestSuite/ModuleSettings/Option/Value');

		for($i = 0; $i < count($option_identifier) && $value == false; $i++)
		{
			if($option_identifier[$i] == $identifier)
			{
				$value = $option_value[$i];
			}
		}

		if($default_fallback && empty($value))
		{
			// Find the default value
			$module_options = call_user_func(array($module_name, "module_setup"));

			for($i = 0; $i < count($module_options) && $value == false; $i++)
			{
				if($module_options[$i]->get_identifier() == $identifier)
				{
					$value = $module_options[$i]->get_default_value();
				}
			}
		}

		return $value;
	}
	public static function read_all_options()
	{
		$module_name = self::module_name();
		$options = array();

		$module_config_parser = new nye_XmlReader(PTS_MODULE_DATA_PATH . $module_name . "/module-settings.xml");
		$option_identifier = $module_config_parser->getXMLArrayValues('PhoronixTestSuite/ModuleSettings/Option/Identifier');
		$option_value = $module_config_parser->getXMLArrayValues('PhoronixTestSuite/ModuleSettings/Option/Value');

		for($i = 0; $i < count($option_identifier); $i++)
		{
			$options[$option_identifier[$i]] = $option_value[$i];
		}

		return $options;
	}
	public static function set_option($identifier, $value)
	{
		pts_module::module_config_save(self::module_name(), array($identifier => $value));
	}
	public static function save_file($file, $contents = null, $append = false)
	{
		// Saves a file for a module

		$save_base_dir = self::save_dir();

		pts_file_io::mkdir($save_base_dir);

		if(($extra_dir = dirname($file)) != "." && !is_dir($save_base_dir . $extra_dir))
		{
			mkdir($save_base_dir . $extra_dir);
		}

		if($append)
		{
			if(is_file($save_base_dir . $file))
			{
				if(file_put_contents($save_base_dir . $file, $contents . "\n", FILE_APPEND) != false)
				{
					return $save_base_dir . $file;
				}
			}
		}
		else
		{
			if(file_put_contents($save_base_dir . $file, $contents) != false)
			{
				return $save_base_dir . $file;
			}
		}

		return false;
	}
	public static function read_file($file)
	{
		$file = self::save_dir() . $file;

		return is_file($file) ? file_get_contents($file) : false;	
	}
	public static function is_file($file)
	{
		$file = self::save_dir() . $file;

		return is_file($file);
	}
	public static function remove_file($file)
	{
		$file = self::save_dir() . $file;

		return is_file($file) && unlink($file);
	}
	public static function copy_file($from_file, $to_file)
	{
		// Copy a file for a module
		$save_base_dir = self::save_dir();

		pts_file_io::mkdir($save_base_dir);

		if(($extra_dir = dirname($to_file)) != "." && !is_dir($save_base_dir . $extra_dir))
		{
			mkdir($save_base_dir . $extra_dir);
		}

		if(is_file($from_file) && (!is_file($save_base_dir . $to_file) || md5_file($from_file) != md5_file($save_base_dir . $to_file)))
		{
			if(copy($from_file, $save_base_dir . $to_file))
			{
				return $save_base_dir . $to_file;
			}
		}

		return false;
	}
	public static function pts_fork_function($function)
	{
		self::pts_timed_function($function, -1);
	}
	public static function pts_timed_function($function, $time)
	{
		if(($time < 0.5 && $time != -1) || $time > 300)
		{
			return;
		}

		if(function_exists('pcntl_fork'))
		{
			$pid = pcntl_fork();

			if($pid != -1)
			{
				if($pid)
				{
					return $pid;
				}
				else
				{
					$loop_continue = true;
					/*
					ML: I think this below check can be safely removed
					$start_id = pts_unique_runtime_identifier();
					 && ($start_id == pts_unique_runtime_identifier() || $start_id == PTS_INIT_TIME)
					*/
					while(pts_test_run_manager::test_run_process_active() !== -1 && is_file(PTS_USER_LOCK) && $loop_continue)
					{
						call_user_func(array(self::module_name(), $function));

						if($time > 0)
						{
							sleep($time);
						}
						else if($time == -1)
						{
							$loop_continue = false;
						}
					}
					exit(0);
				}
			}
		}
		else
		{
			trigger_error('php-pcntl must be installed for the ' . self::module_name() . ' module.', E_USER_ERROR);
		}
	}
	private static function module_name()
	{
		$module_name = 'unknown';

		if(($current = pts_module_manager::get_current_module()) != null)
		{
			$module_name = $current;
		}
		else
		{
			$bt = debug_backtrace();

			for($i = 0; $i < count($bt) && $module_name == "unknown"; $i++)
			{
				if($bt[$i]["class"] != "pts_module")
				{
					$module_name = $bt[$i]["class"];
				}
			}
		}

		return $module_name;
	}
}

?>
