<?php

/**
 * This file contains a (static) class that will track some debug informations
 * if debug is on.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta 2
 *
 */

if (!defined('ELK'))
	die('No access...');

class Debug
{
	/**
	 * This is used to remember if the debug is on or off
	 * @var bool
	 */
	private static $_track = true;

	/**
	 * A list of known debug entities (here to preserve a kind of order)
	 * @var array|array
	 */
	private static $_debugs = array(
		'templates' => array(),
		'sub_templates' => array(),
		'language_files' => array(),
		'stylesheets' => array(),
		'javascript' => array(),
		'hooks' => array(),
		'files_included' => array(),
		'tokens' => array(),
	);

	/**
	 * Holds the output ot the getrusage php function
	 * @var array|array
	 */
	private static $_rusage = array();

	/**
	 * Holds All the cache hits for a page load
	 * @var array|string
	 */
	private static $_cache_hits = array();

	/**
	 * Number of times the cache has been used
	 * @var int
	 */
	private static $_cache_count = 0;

	/**
	 * All the queries executed
	 * @var array|array
	 */
	private static $_db_cache = array();

	/**
	 * Number of queries
	 * @var int
	 */
	private static $_db_count = 0;

	/**
	 * Some generic "system" debug info
	 * @var array|string
	 */
	private static $_system = array();


	/**
	 * Adds a new generic debug entry
	 *
	 * @param string the kind of debug entry
	 * @param mixed string or array of the entry to show
	 */
	public static function add($type, $value)
	{
		if (!self::$_track)
			return;

		if (is_array($value))
			self::$_debugs[$type] = array_merge(self::$_debugs[$type], $value);
		else
			self::$_debugs[$type][] = $value;
	}

	/**
	 * Adds a new cache hits
	 *
	 * @param array contains the relevant cache info, in the form:
	 *         d => method: put or get
	 *         k => cache key
	 *         t => time taken to get/put the entry
	 *         s => length of the serialized value
	 */
	public static function cache($value)
	{
		if (!self::$_track)
			return;

		self::$_cache_hits[] = $value;
		self::$_cache_count++;
	}

	/**
	 * Return the number of cache hits
	 *
	 * @return int
	 */
	public static function cache_count()
	{
		return self::$_cache_count;
	}

	/**
	 * Adds a new database query
	 *
	 * @param array contains the relevant queries info, in the form:
	 *         q => the query string (only for the first 50 queries, after that only a "...")
	 *         f => the file in which the query has been executed
	 *         l => the line at which the query has been executed
	 *         s => seconds at which the query has been executed into the request
	 *         t => time taken by the query
	 */
	public static function db($value)
	{
		if (!self::$_track)
			return;

		self::$_db_cache[] = $value;
		self::$_db_count++;
	}

	/**
	 * Merges the values passed with the current database entries
	 *
	 * @param array|array An array of queries info, see the db method for details
	 */
	public static function merge_db($value)
	{
		if (!self::$_track)
			return;

		self::$_db_cache = array_merge($value, self::$_db_cache);
		self::$_db_count = count(self::$_db_cache) + 1;
	}

	/**
	 * Return the current database entries
	 *
	 * @return array
	 */
	public static function get_db()
	{
		if (!self::$_track)
			return;

		return self::$_db_cache;
	}

	/**
	 * Adds a new getrusage value (by default two are added: one at the beginning
	 * of the script execution and one at the end
	 *
	 * @param string $point can be end or start depending on when the function
	 *               is called
	 * @param mixed value of getrusage or null to let the method call it
	 */
	public static function rusage($point, $rusage = null)
	{
		if (!function_exists('getrusage') || !self::$_track)
			return;

		if ($rusage === null)
			self::$_rusage[$point] = getrusage();
		else
			self::$_rusage[$point] = $rusage;
	}

	/**
	 * Enables tracking of debug entries
	 */
	public static function on()
	{
		self::$_track = true;
	}

	/**
	 * Disables tracking of debug entries
	 */
	public static function off()
	{
		self::$_track = false;
	}

	/**
	 * Toggles the visibility of the queries
	 */
	public static function toggleViewQueries()
	{
		$_SESSION['view_queries'] = $_SESSION['view_queries'] == 1 ? 0 : 1;
	}

	/**
	 * Collects some other generic system informations necessary for the
	 * debug screen
	 */
	private static function _prepare_last_bits()
	{
		global $context;

		if (empty($_SESSION['view_queries']))
			$_SESSION['view_queries'] = 0;

		$files = get_included_files();
		$total_size = 0;
		for ($i = 0, $n = count($files); $i < $n; $i++)
		{
			if (file_exists($files[$i]))
				$total_size += filesize($files[$i]);
			Debug::add('files_included', strtr($files[$i], array(BOARDDIR => '.')));
		}

		if (!empty(self::$_db_cache))
			$_SESSION['debug'] = self::$_db_cache;

		// Compute some system info, if we can
		self::$_system['system_type'] = php_uname();
		self::$_system['server_load'] = detectServerLoad();
		self::$_system['script_mem_load'] = round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB';

		// getrusage() information is CPU time, not wall clock time like microtime, *nix only
		Debug::rusage('end');

		if (!empty(self::$_rusage))
		{
			self::$_system['script_cpu_load'] = (self::$_rusage['end']['ru_utime.tv_sec'] - self::$_rusage['start']['ru_utime.tv_sec'] + (self::$_rusage['end']['ru_utime.tv_usec'] / 1000000))  . ' / ' . (self::$_rusage['end']['ru_stime.tv_sec'] - self::$_rusage['start']['ru_stime.tv_sec'] + (self::$_rusage['end']['ru_stime.tv_usec'] / 1000000));
		}

		self::$_system['browser'] = $context['browser_body_id'] . ' <em>(' . implode('</em>, <em>', array_reverse(array_keys($context['browser'], true))) . ')</em>';

		// What tokens are active?
		if (isset($_SESSION['token']))
			Debug::add('tokens', array_keys($_SESSION['token']));
	}

	/**
	 * This function shows the debug information tracked
	 */
	public static function display()
	{
		global $scripturl, $modSettings;
		global $txt;

		self::_prepare_last_bits();

		// Gotta have valid HTML ;).
		$temp = ob_get_contents();
		ob_clean();

		echo preg_replace('~</body>\s*</html>~', '', $temp), '
		<div id="debug_logging_wrapper">
			<div id="debug_logging" class="smalltext">';

		foreach (self::$_system as $key => $value)
			if (!empty($value))
				echo '
				', $txt['debug_' . $key], $value, '<br />';

		$expandable = array('hooks', 'files_included');

		foreach (self::$_debugs as $key => $value)
		{
			if (in_array($key, $expandable))
			{
				$pre = ' (<a href="javascript:void(0);" onclick="document.getElementById(\'debug_' . $key . '\').style.display = \'inline\'; this.style.display = \'none\'; return false;">' . $txt['debug_show'] . '</a><span id="debug_' . $key . '" style="display: none;">';
				$post = '</span>)';
			}
			else
			{
				$pre = '';
				$post = '';
			}
			echo '
				', $txt['debug_' . $key], count($value), ' - ' . $pre . '<em>', implode('</em>, <em>', $value), '</em>.' . $post . '<br />';
		}

		// If the cache is on, how successful was it?
		if (!empty($modSettings['cache_enable']) && !empty(self::$_cache_hits))
		{
			$entries = array();
			$total_t = 0;
			$total_s = 0;
			foreach (self::$_cache_hits as $cache_hit)
			{
				$entries[] = $cache_hit['d'] . ' ' . $cache_hit['k'] . ': ' . sprintf($txt['debug_cache_seconds_bytes'], comma_format($cache_hit['t'], 5), $cache_hit['s']);
				$total_t += $cache_hit['t'];
				$total_s += $cache_hit['s'];
			}

			echo '
				', $txt['debug_cache_hits'], Debug::cache_count(), ': ', sprintf($txt['debug_cache_seconds_bytes_total'], comma_format($total_t, 5), comma_format($total_s)), ' (<a href="javascript:void(0);" onclick="document.getElementById(\'debug_cache_info\').style.display = \'inline\'; this.style.display = \'none\'; return false;">', $txt['debug_show'], '</a><span id="debug_cache_info" style="display: none;"><em>', implode('</em>, <em>', $entries), '</em></span>)<br />';
		}

		// Want to see the querys in a new windows?
		echo '
				<a href="', $scripturl, '?action=viewquery" target="_blank" class="new_win">', sprintf($txt['debug_queries_used'], self::$_db_count), '</a><br />';

		if ($_SESSION['view_queries'] == 1 && !empty(self::$_db_cache))
			self::_show_queries();

		// Or show/hide the querys in line with all of this data
		echo '
				<a href="' . $scripturl . '?action=viewquery;sa=hide">', $txt['debug_' . (empty($_SESSION['view_queries']) ? 'show' : 'hide') . '_queries'], '</a>
			</div>
		</div>
	</body></html>';
	}

	/**
	 * Displays a page with all the queries executed during the "current"
	 * page load and allows to EXPLAIN them
	 *
	 * @param integer the id of the query to EXPLAIN, if -1 no queries are explained
	 */
	public static function viewQueries($query_id)
	{
		$queries_data = array();

		$query_analysis = new Query_Analysis();

		foreach ($_SESSION['debug'] as $q => $query_data)
		{
			$queries_data[$q] = $query_analysis->extractInfo($query_data);

			// Explain the query.
			if ($query_id == $q && $queries_data[$q]['is_select'])
			{
				$queries_data[$q]['explain'] = $query_analysis->doExplain();
			}
		}

		return $queries_data;
	}

	/**
	 * Displays a list of queries executed during the current
	 * page load
	 */
	private static function _show_queries()
	{
		global $scripturl, $txt;

		foreach (self::$_db_cache as $q => $qq)
		{
			$is_select = strpos(trim($qq['q']), 'SELECT') === 0 || preg_match('~^INSERT(?: IGNORE)? INTO \w+(?:\s+\([^)]+\))?\s+SELECT .+$~s', trim($qq['q'])) != 0;

			// Temporary tables created in earlier queries are not explainable.
			if ($is_select)
			{
				foreach (array('log_topics_unread', 'topics_posted_in', 'tmp_log_search_topics', 'tmp_log_search_messages') as $tmp)
					if (strpos(trim($qq['q']), $tmp) !== false)
					{
						$is_select = false;
						break;
					}
			}
			// But actual creation of the temporary tables are.
			elseif (preg_match('~^CREATE TEMPORARY TABLE .+?SELECT .+$~s', trim($qq['q'])) != 0)
				$is_select = true;

			// Make the filenames look a bit better.
			if (isset($qq['f']))
				$qq['f'] = preg_replace('~^' . preg_quote(BOARDDIR, '~') . '~', '...', $qq['f']);

			echo '
		<strong>', $is_select ? '<a href="' . $scripturl . '?action=viewquery;qq=' . ($q + 1) . '#qq' . $q . '" target="_blank" class="new_win" style="text-decoration: none;">' : '', nl2br(str_replace("\t", '&nbsp;&nbsp;&nbsp;', htmlspecialchars(ltrim($qq['q'], "\n\r"), ENT_COMPAT, 'UTF-8'))) . ($is_select ? '</a></strong>' : '</strong>') . '<br />
		&nbsp;&nbsp;&nbsp;';
			if (!empty($qq['f']) && !empty($qq['l']))
				echo sprintf($txt['debug_query_in_line'], $qq['f'], $qq['l']);

			if (isset($qq['s'], $qq['t']) && isset($txt['debug_query_which_took_at']))
				echo sprintf($txt['debug_query_which_took_at'], round($qq['t'], 8), round($qq['s'], 8)) . '<br />';
			elseif (isset($qq['t']))
				echo sprintf($txt['debug_query_which_took'], round($qq['t'], 8)) . '<br />';
			echo '
		<br />';
		}
	}
}