<?php
/* Copyright (c) 2011, Sam Clarke
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the <organization> nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL SAM CLARKE BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * The TV Db API key to use. You can register for one here: http://thetvdb.com/?tab=apiregister
 */
define('TVDB_API_KEY', '<YOUR-API-KEY>');

/**
 * The TV Db API class
 *
 * Allows use of The TVDb API easily.
 *
 * Requires simplexml and either cURL, allow_url_fopen or fsockopen enabled to work.
 * @todo Add multi lingual and banner support.
 * Maybe allow search by IMDB id using http://thetvdb.com/api/GetSeriesByRemoteID.php?imdbid=tt0411008 although most episodes dont seem to have an imdb id
 * @author Sam Clarke <sam@samclarke.com>
 * @version 1.0
 * @license http://opensource.org/licenses/bsd-license The BSD License
 */
class TVDb
{
	/**
	 * URI of the TVDb site
	 */
	const TVDB_URI     = 'http://thetvdb.com/';

	/**
	 * URI of the main TVDb API
	 */
	const TVDB_API_URI = 'http://thetvdb.com/api/';

	/**
	 * XML mirror to use
	 * @access public
	 * @var string
	 */
	private $tvdb_xml_mirror_url    = self::TVDB_API_URI;

	/**
	 * The banner mirror to use
	 * @access public
	 * @var string
	 */
	private $tvdb_banner_mirror_url = self::TVDB_API_URI;

	/**
	 * The ZIP mirror to use
	 * @access public
	 * @var string
	 */
	private $tvdb_zip_mirror_url    = self::TVDB_API_URI;

	/**
	 * The TVDb server time
	 * @access public
	 * @var string
	 */
	private $tvdb_time;


	/**
	 * Currently there is only 1 mirror avalible. Untill this changes it seems like
	 * a bad idea to send an extra request to the main server to find out that it is the
	 * only mirror. Because of this the load mirrors currently defaults to not loading
	 * mirrors and just using the main server.
	 * @param bool $load_servertime
	 * @param bool $load_mirrors
	 */
	public function  __construct($load_servertime=false, $load_mirrors=false)
	{
		if($load_mirrors)
			$this->load_mirror();

		if($load_servertime)
			$this->load_server_time();
	}

	/**
	 * Returns TVDb server timestamp.
	 *
	 * The $load_servertime param in the constructor must be set to true
	 * overwise this will not return a time.
	 * @return string
	 */
	public function get_server_time()
	{
		return $this->tvdb_time;
	}

	/**
	 * Loads the time of the TVDb server
	 */
	private function load_server_time()
	{
		$time = $this->get_xml_url_contents(self::TVDB_API_URI . '/Updates.php?type=none');
		
		if($time !== false)
			$this->tvdb_time = (string)$time->Time;
	}


	/**
	 * Loads the three mirror servers to use
	 */
	private function load_mirror()
	{
		$mirrors = $this->get_xml_url_contents(self::TVDB_API_URI . TVDB_API_KEY . '/mirrors.xml');

		if($mirrors === false)
			return false;

		//1 xml files
		//2 banner files
		//4 zip files
		$this->tvdb_xml_mirror_url    = $this->pick_random_mirror($mirrors, 1);
		$this->tvdb_banner_mirror_url = $this->pick_random_mirror($mirrors, 2);
		$this->tvdb_zip_mirror_url    = $this->pick_random_mirror($mirrors, 4);
	}

	private function pick_random_mirror(SimpleXMLElement $mirrors, $typemask)
	{
		$mirrors_count = count($mirrors) - 1;
		
		while(($mirror = $mirrors[rand(0, $mirrors_count)]))
		{
			if(!($mirror->Mirror->typemask & $typemask))
				continue;

			return (string)$mirror->Mirror->mirrorpath;
		}
	}

	/**
	 * Gets the contents of a URL and returns the simplexml parsed result
	 * @param string $url
	 * @return SimpleXMLElement|false
	 */
	private function get_xml_url_contents($url)
	{
		$data = $this->get_url_contents($url);

		if($data === false)
			return false;

		return simplexml_load_string($data);
	}

	/**
	 * Gets the contents of a URL
	 *
	 * Attempts to use the following methods:
	 *  cURL
	 *  fopen
	 *  fsockopen
	 *
	 * Returns boolean false if it fails.
	 * @param string $url
	 * @return string|false
	 */
	private function get_url_contents($url)
	{
		$contents = NULL;

		// 2xx is successful everything else failed as far as we are concerned
		// so all none 2xx status codes will return false
		ini_set( 'user_agent', 'User-Agent: CITVDB/1.0' );// for fopen
		if(function_exists('curl_init'))
		{
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_USERAGENT, 'User-Agent: CITVDB/1.0');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$contents  = curl_exec($ch);
			$http_code = (string)curl_getinfo($ch, CURLINFO_HTTP_CODE);

			if($http_code{0} != 2)
				return false;

			curl_close($ch);
		}
		else if(($fp = fopen($url, 'r')))
		{
			if(!preg_match("/2[0-9]{2}/", $http_response_header[0]))
				return false;

			for(;($data = fread($fp, 8192));)
				$contents .= $data;

			fclose($fp);
		}
		elseif(($url_info = parse_url($url)))
		{
			if($url_info['scheme'] == 'https')
				$fp = fsockopen( 'ssl://' . $url_info['host'], 443, $errno, $errstr, 30);
			else
				$fp = fsockopen( $url_info['host'], 80, $errno, $errstr, 30);

			if(!$fp)
				return false;

			$out = 'GET ' . (isset($url_info['path'])? $url_info['path']: '/') .
				(isset($url_info['query'])? '?' . $url_info['query']: '') . " HTTP/1.0\r\n";
			$out .= "Host: {$url_info['host']}\r\n";
			$out .= "User-Agent: CITVDB/1.0\r\n";
			$out .= "Connection: Close\r\n\r\n";

			fwrite($fp, $out);
			for(;!feof( $fp );)
				$contents .= fread($fp, 8192);

			list($headers, $contents) = preg_split("/(\r\n|\r|\n){2}/", $contents, 2);

			if(!preg_match("/2[0-9]{2}/", $headers[0]))
				return false;
		}
		else
			return false;

		return $contents;
	}

	/**
	 * Finds TV shows with $name in their name.
	 *
	 * TVDb appears to return a max of 99 results.
	 *
	 * @param string $name
	 * @return array|false Array of TV Shows or false
	 */
	public function search_tv_shows($name)
	{
		$results = $this->get_xml_url_contents(self::TVDB_API_URI . '/GetSeries.php?seriesname=' . urlencode($name));
		$shows   = array();

		if($results === false)
			return false;

		foreach($results as $result)
			$shows[] = new TVDbShow($result);;

		return $shows;
	}

	/**
	 * Gets a TV Show by its TVDb ID
	 *
	 * Will attempt to get the compressed Zip version if PHP has Zip support.
	 *
	 * Set $include_episodes to false unless you need the episode data for
	 * the entire series. If there are a lot of episodes it could take a long
	 * time to download even when compressed.
	 * @param int  $id
	 * @param bool $include_episodes If to get the data for the episodes too.
	 * @return TVDbShow|false
	 */
	public function get_tv_show_by_id($id, $include_episodes=true)
	{
		if(!$include_episodes)
		{
			$data = $this->get_xml_url_contents(self::TVDB_URI . 'data/series/' . urlencode($id) . '/');

			if($data === false)
				return false;

			return new TVDbShow($data->Series);
		}

		// get the zipped file if PHP has ZIP
		if(class_exists('ZipArchive'))
		{
			$zipped_data = $this->get_url_contents($this->tvdb_zip_mirror_url . TVDB_API_KEY
									. '/series/' . urlencode($id) . '/all/en.zip');

			if($zipped_data === false)
				return false;

			$tmp = tempnam(sys_get_temp_dir(), 'TVDBZIP');
			file_put_contents($tmp, $zipped_data);
			unset($zipped_data);

			$zip = new ZipArchive();
			$zip->open($tmp);

			$result = simplexml_load_string($zip->getFromName('en.xml'));

			$zip->close();
			unlink($tmp);
		}
		else
			$result = $this->get_xml_url_contents($this->tvdb_xml_mirror_url . TVDB_API_KEY
								. '/series/' . urlencode($id) . '/all/en.xml');

		if($result === false)
			return false;

		$show = new TVDbShow($result->Series);
		
		foreach($result->Episode as $epi)
			$show->episodes[] = new TVDbEpisode($epi);

		return $show;
	}

	/**
	 * Gets a TV Episode by its TVDb ID
	 * @param int $id TVDb Edpisode ID
	 * @return TVDbEpisode|false
	 */
	public function get_tv_episode_by_id($id)
	{
		$data = $this->get_xml_url_contents($this->tvdb_xml_mirror_url . TVDB_API_KEY
									. '/episodes/' . urlencode($id) . '/en.xml');

		if($data === false)
				return false;

		return new TVDbEpisode($data->Episode);
	}

	/**
	 * Gets a TV episode
	 * @param int $tvshow_id TVDb show ID
	 * @param int $series    Season number
	 * @param int $episode   Episode number
	 * @return TVDbEpisode|false
	 */
	public function get_tv_episode($tvshow_id, $series, $episode)
	{
		$data = $this->get_xml_url_contents($this->tvdb_xml_mirror_url . TVDB_API_KEY
							. '/series/' . urlencode($tvshow_id) . '/default/'
							. urlencode($series) . '/'
							. urlencode($episode) . '/en.xml');
		
		if($data === false)
				return false;

		return new TVDbEpisode($data->Episode);
	}
}

class TVDbShow
{
	/**
	 * Contains a list of TVDbEpisode objects.
	 *
	 * Only populated by the get_tv_show_by_id function.
	 * @access public
	 * @var array
	 */
	public $episodes = array();

	/**
	 * TVDb ID
	 * @access public
	 * @var int
	 */
	public $id;

	/**
	 * Array of actor names
	 * @access public
	 * @var array
	 */
	public $actors = array();

	/**
	 * Day on which TV show is shown
	 * @access public
	 * @var string
	 */
	public $airs_day_of_week;

	/**
	 * Time TV show is/was shown
	 * @access public
	 * @var string
	 */
	public $airs_time;

	/**
	 * Date TV show was first shown
	 * @access public
	 * @var string
	 */
	public $first_aired;

	/**
	 * Array of genres
	 * @access public
	 * @var array
	 */
	public $genre = array();

	/**
	 * IMDB id
	 * @access public
	 * @var string
	 */
	public $IMDB_id;

	/**
	 * Language this information is in
	 *
	 * Should be 'en', 'fr', ect.
	 * @access public
	 * @var string
	 */
	public $language;

	/**
	 * Network TV show is shown on
	 * @access public
	 * @var string
	 */
	public $network;

	/**
	 * TVDb network ID?
	 * @access public
	 * @var string
	 */
	public $network_id;

	/**
	 * Overview of the TV show
	 * @access public
	 * @var string
	 */
	public $overview;

	/**
	 * TVDb rating of the TV show
	 * @access public
	 * @var string
	 */
	public $rating;

	/**
	 * Number of ratings
	 * @access public
	 * @var string
	 */
	public $rating_count;

	/**
	 * The shows runttime
	 * @access public
	 * @var string
	 */
	public $runtime;

	/**
	 * zap2it ID
	 * @access public
	 * @var string
	 */
	public $zap2it_id;

	/**
	 * TV Shows name
	 * @access public
	 * @var string
	 */
	public $name;

	/**
	 * Status of the TV show. If it has ended, ect.
	 * @access public
	 * @var string
	 */
	public $status;

	/**
	 * Time stamp of when the TV show was last updated
	 * @access public
	 * @var string
	 */
	public $last_updated;

	/**
	 * @param SimpleXMLElement $data SimpleXMLElement containing all the TV Show data
	 */
	public function  __construct(SimpleXMLElement $data=null)
	{
		if($data !== null)
		{
			$this->id               = (int)$data->id;
			$this->actors           = preg_split("/\|/", $data->Actors, -1, PREG_SPLIT_NO_EMPTY);
			$this->airs_day_of_week = (string)$data->Airs_DayOfWeek;
			$this->airs_time        = (string)$data->Airs_Time;
			$this->first_aired      = (string)$data->FirstAired;
			$this->genre            = preg_split("/\|/", $data->Genre, -1, PREG_SPLIT_NO_EMPTY);
			$this->IMDB_id          = (string)$data->IMDB_ID;
			$this->language         = (string)$data->language;
			$this->network          = (string)$data->Network;
			$this->network_id       = (string)$data->NetworkID;
			$this->overview         = (string)$data->Overview;
			$this->rating           = (string)$data->Rating;
			$this->rating_count     = (string)$data->RatingCount;
			$this->runtime          = (string)$data->Runtime;
			$this->zap2it_id        = (string)$data->zap2it_id;
			$this->name             = (string)$data->SeriesName;
			$this->status           = (string)$data->Status;
			$this->last_updated     = (string)$data->lastupdated;
		}
	}
}

class TVDbEpisode
{
	public $id;
	public $combined_episodenumber;
	public $combined_season;
	public $DVD_chapter;
	public $DVD_discid;
	public $DVD_episodenumber;
	public $DVD_season;
	public $directors = array();
	public $episode_name;
	public $episode_number;
	public $first_aired;
	public $guest_stars = array();
	public $IMDB_id;
	public $language;
	public $overview;
	public $production_code;
	public $rating;
	public $rating_count;
	public $season_number;
	public $writers = array();
	public $absolute_number;
	public $airsafter_season;
	public $airsbefore_episode;
	public $airsbefore_season;
	public $lastupdated;
	public $seasonid;
	public $seriesid;

	/**
	 * @param SimpleXMLElement $data SimpleXMLElement containing all the TV Show data
	 */
	public function  __construct(SimpleXMLElement $data=null)
	{
		if($data !== null)
		{
			$this->id                     = (int)$data->id;
			$this->combined_episodenumber = (string)$data->Combined_episodenumber;
			$this->combined_season        = (string)$data->Combined_season;
			$this->DVD_chapter            = (string)$data->DVD_chapter;
			$this->DVD_discid             = (string)$data->DVD_discid;
			$this->DVD_episodenumber      = (string)$data->DVD_episodenumber;
			$this->DVD_season             = (string)$data->DVD_season;
			$this->directors              = preg_split("/\|/", $data->Director, -1, PREG_SPLIT_NO_EMPTY);
			$this->episode_name           = (string)$data->EpisodeName;
			$this->episode_number         = (string)$data->EpisodeNumber;
			$this->first_aired            = (string)$data->FirstAired;
			$this->guest_stars            = preg_split("/\|/", $data->GuestStars, -1, PREG_SPLIT_NO_EMPTY);
			$this->IMDB_id                = (string)$data->IMDB_ID;
			$this->language               = (string)$data->Language;
			$this->overview               = (string)$data->Overview;
			$this->production_code        = (string)$data->ProductionCode;
			$this->rating                 = (string)$data->Rating;
			$this->rating_count           = (string)$data->RatingCount;
			$this->season_number          = (string)$data->SeasonNumber;
			$this->writers                = preg_split("/\|/", $data->Writer, -1, PREG_SPLIT_NO_EMPTY);
			$this->absolute_number        = (string)$data->absolute_number;
			$this->airsafter_season       = (string)$data->airsafter_season;
			$this->airsbefore_episode     = (string)$data->airsbefore_episode;
			$this->airsbefore_season      = (string)$data->airsbefore_season;
			$this->lastupdated            = (string)$data->lastupdated;
			$this->seasonid               = (string)$data->seasonid;
			$this->seriesid               = (string)$data->seriesid;
		}
	}
}
