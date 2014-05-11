<?php
include "config.php";
global $conf;

// SET UP GLOBALS FOR DRUPAL INTEGRATION
global $needs_drupal;
global $needs_auth;
$needs_drupal = true;
$needs_auth = true;
require_once "/home/lcc_web/lafayettecc.org/civicrm_custom_scripts/civicrm_api_connect.php";

//force_debug($_SESSION);
//force_debug($_COOKIE);
//dd($user);


$crm = new CiviCRM();

// RESET THE SESSION
if (isset($_GET['reset']))
{
	unset($_SESSION['lcc_volunteers_user']);
	Header('Location: index.php');
}

// SETUP THE GLOBALS
// globals
global $query_count;
$query_count = 0;
global $query_time;
$query_time = 0;
global $query_history;
$query_history = Array();


$msg = a_get($_SESSION, 'msg', NULL);
unset($_SESSION['msg']);

$object = a_get($_GET, 'object', NULL);
$action = a_get($_GET, 'action', NULL);
$id = a_get($_GET, 'id', NULL);

//for ajax calls
$volunteer_api_key = 'qopnioqnonvldnainvoa8gq928h91n9f8h9nvon92n929n9pv8v912nionpion';
$ical_token = 'ZNOZNOPAINN4';

// set up db object
$_volunteer_db = new VDataBase($v_conf['dbname'], $v_conf['dbhost'], $v_conf['dbuser'], $v_conf['dbpass']);

//actions and permissions
$acl = Array(
	'cron-email' => array('default' => 0),
	'sync' => array('default' => 2),
	'show' => array('default' => 1),
	'ical' => array('default' => 0),
	'new' => array('default' => 2, 'exclusion' => 1),
	'edit' => array('default' => 2, 'exclusion' => 1, 'self' => 1),
	'unassign' => array('default' => 2),
	'assign' => array('default' => 2));

//check permissions before going further
$vuser = authenticate();
$args = array('action' => $action, 'object'=>$object, 'id'=>$id);

if (!check_perms($args))
{
	$_SESSION['msg']="You don't have permission to do that.";
	Header('Location: ?action=error');
}





// PROCESS POSTS AND STUFF FIRST
//assignments
if ($action == 'assign')
{
	$jid = a_get($_GET,'jid',NULL);
	$pid = a_get($_GET,'pid',NULL);
	if ($jid && $pid)
		assign_job_to_volunteer($jid, $pid);
}

if ($action == 'unassign')
{
	$jid = a_get($_GET,'jid',NULL);
	$pid = a_get($_GET,'pid',NULL);
	if ($jid && $pid)
		delete_assignment($jid, $pid);
}

// PROCESS POSTED DATA
if ($object == 'exclusion'  and ($action=="edit" or $action=="new") and isset($_POST['submit']))
{
	$eid = $id;
	$keys = Array ('id','person_id','datetime_start','datetime_end','reason');
	$exclusion = Array();
	foreach ($keys as $key) $exclusion[$key] = $_POST[$key];

	if ($_POST['submit'] == 'DELETE')
	{
		$_volunteer_db->query("DELETE FROM exclusions WHERE id={$eid}");
		$_SESSION['msg'] = '"off time" deleted';
		Header("Location: ?action=show&object=person&id={$exclusion['person_id']}");
		exit();
	}
	if (isset($_POST['save_as_new']) && $_POST['save_as_new'] == 1)
	{
		unset($exclusion['id']);
		$id = $_volunteer_db->save('exclusions',$exclusion);
		$_SESSION['msg'] = 'new "off time" created';
		$url = "?action=show&object=exclusion&id=$id";
		Header("Location: $url");
		exit();
	}
	else
	{
		$id = $_volunteer_db->save('exclusions',$exclusion);
		$_SESSION['msg'] = '"off time" saved';
		$url = "?action=show&object=exclusion&id=$id";
		Header("Location: $url");
		exit();
	}
}

// JOB EDIT
if ($object == 'job'  and ($action=="edit" or $action=="new") and isset($_POST['submit']))
{
	$jid = $_POST['id'];
	$keys = Array ('id','name','datetime_start','datetime_end','description','is_template','default_group');
	$required_keys = Array ('name','datetime_start','datetime_end','description','default_group');
	$job = Array();
	foreach ($keys as $key) $job[$key] = $_POST[$key];

	if (isset($_POST['repeat'])) $repeat = $_POST['repeat'];
	else $repeat = 0;

	// check for delete first
	if ($_POST['submit'] == 'DELETE')
	{
		$_volunteer_db->query("DELETE FROM jobs WHERE id={$jid}");
		$_SESSION['msg'] = 'job deleted';
		Header("Location: ?");
		exit();
	}

	$is_valid = validate_form($required_keys);
	if (! $is_valid )
	{
		$msg = 'Please fill out the entire form';
	}
	else
	{
		if (isset($_POST['save_as_new']) and $_POST['save_as_new'])
		{
			unset($job['id']);
			$_SESSION['msg'] = 'New Jobs Created: ' . ($repeat + 1);
		}
		else
		{
			$_SESSION['msg'] = 'Job Edited';
			if ($repeat > 1) $_SESSION['msg'] = 'Job Edited. New Jobs Created: ' . ($repeat);
		}
	}

	// do the actual saving of jobs
	$one_week = 60 * 60 * 24 * 7;
	$one_week = new DateInterval('P1W');

	// first save the job we have, then if we need to process any repeats, we modify the job and save it.
	$id = $_volunteer_db->save('jobs', $job);


	for ($i = 1; $i < $repeat; $i++)
	{
		// remove the job id so that it gets saved as a new job.
		unset($job['id']);
		unset($job['is_template']);

		// modify job to have same exact parameters but occur one week later.
		$new_start = new DateTime($job['datetime_start']);
		$new_start = $new_start->add($one_week);
		$new_end = new DateTime($job['datetime_end']);
		$new_end = $new_end->add($one_week);
		$job['datetime_start'] = $new_start->format("Y-m-d H:i:s");
		$job['datetime_end'] = $new_end->format("Y-m-d H:i:s");
		$id = $_volunteer_db->save('jobs', $job);
	}

	$redirect_url = "?action=show&object=job&id=$id";
	Header("Location: $redirect_url");
}

// PERSON EDIT
if ($object == 'person'  and ($action=="edit" or $action=="new") and isset($_POST['submit']))
{
	$pid = $_REQUEST['id'];
	$vusername = mysql_real_escape_string($_POST['username']);
	$password = mysql_real_escape_string($_POST['password']);
	$hash = md5($password);
	$password = '';
	$q = "UPDATE people SET username='{$vusername}', password='{$hash}' WHERE people.id={$pid}";
	//_debug($q);
	$_volunteer_db->query($q);
	$_SESSION['msg'] = 'User Details Changed';
	Header("Location: ?action=show&object=person&id={$pid}");
}



if ($action == 'cron-email')
{
	Header('Content-type: text/plain');
	print "selected action: cron-email";
	if (a_get($_GET,'key',NULL) == $volunteer_api_key)
	{
		print "Preparing email...";
		// api_key is valid, do emailing
		$email_data = array();

		$encouraging_verses = array(
			'I pray that you may be active in sharing your faith, so that you will have a full understanding of every good thing we have in Christ. Your love has given me great joy and encouragement, because you, brother, have refreshed the hearts of the saints. -- Philemon 1:6-7',
			'You are generous because of your faith. And I am praying that you will really put your generosity to work, for in so doing you will come to an understanding of all the good things we can do for Christ. I myself have gained much joy and comfort from your love, my brother, because your kindness has so often refreshed the hearts of God\'s people. -- Philemon 1:6-7 NLT',
			'For we are God\'s handiwork, created in Christ Jesus to do good works, which God prepared in advance for us to do. -- Ephesians 2:10',
			'Let us not become weary in doing good, for at the proper time we will reap a harvest if we do not give up. -- Galatians 6:9',
			'Therefore, as we have opportunity, let us do good to all people, especially to those who belong to the family of believers. -- Galatians 6:10',
			'[Jesus] I have told you this so that my joy may be in you and that your joy may be complete. My command is this: Love each other as I have loved you. Greater love has no one than this: to lay down one\'s life for one\'s friends. -- John 15:11-13'
			);

		$email_msg = "
LCC VOLUNTEER SCHEDULING SYSTEM
========================================
[[verse]]

Your Account Page: [[url]]
Your Account Username: [[username]]

Dear [[name]],

Thank you for being a faithful volunteer. Your service to the church refreshes people in ways you may never know. Here are your upcoming assignments and the times off you have requested.


YOUR UPCOMING ASSIGNMENTS
----------------------------------------
NOTE: Remember that you can add your assignments to your calendar software by visiting your Account Page and clicking one of the three calendar link buttons depending on which calendar software you use.

[[joblist]]


YOUR UPCOMING TIMES OFF
----------------------------------------
[[exclusions]]

========================================

Thank you once again!

========================================
Click This Link to View Your Current Assignments Online:
[[url]]
========================================";

		$volunteers = $_volunteer_db->get_volunteers();
		foreach ($volunteers as $volunteer)
		{
			//if ($volunteer['pid'] != 1) continue;
			$jobs = $_volunteer_db->get_upcoming_jobs_for_person($volunteer['pid']);
			$exclusions = $_volunteer_db->get_exclusions_for_volunteer($volunteer['pid']);
			$email_data[] = array('volunteer' => $volunteer, 'exclusions' => $exclusions, 'jobs' => $jobs);
		}

		foreach ($email_data as $this_email)
		{
			if (! $this_email['exclusions'] AND ! $this_email['jobs']) continue;
			$volunteer_url = "${site_url}/?action=show&object=person&id={$this_email['volunteer']['pid']}";
			$to = $this_email['volunteer']['email'];
			$subject = "[LCC SERVE] :: Jobs for {$this_email['volunteer']['name']}";
			$from = "FROM: \"Pastor Jeff\" <pastor@lafayettecc.org>";

			// format the exclusions
			$exclusion_template = "
from: [[datetime_start]]
until: [[datetime_end]]
[[reason]]\n";

			$exclusions_text = "";
			foreach ($this_email['exclusions'] as $exclusion)
			{
				$tmpvar = $exclusion_template;
				foreach ($exclusion as $key => $value) $tmpvar = str_replace("[[$key]]", $value, $tmpvar);
				$exclusions_text .= $tmpvar;
			}

			// format the jobs
			$job_template = "
[[nicedate]] :: [[name]]
----------------------------------------
\tresponsibility: [[note]]
\tfrom: [[datetime_start]]
\tuntil: [[datetime_end]]
\t[[description]]\n\n";

			$jobs_text = "";
			foreach ($this_email['jobs'] as $job)
			{
				$nicedate = nice_date($job['datetime_start']);
				$tmpvar = $job_template;
				foreach ($job as $key => $value) $tmpvar = str_replace("[[$key]]", $value, $tmpvar);
				$tmpvar = str_replace("[[nicedate]]", $nicedate, $tmpvar);
				$jobs_text .= $tmpvar;
			}


			$msg = str_replace('[[name]]',$this_email['volunteer']['name'], $email_msg);
			$msg = str_replace('[[exclusions]]',$exclusions_text, $msg);
			$msg = str_replace('[[joblist]]',$jobs_text, $msg);
			$msg = str_replace('[[verse]]',$encouraging_verses[array_rand($encouraging_verses)], $msg);
			$msg = str_replace('[[url]]',$volunteer_url, $msg);
			$msg = str_replace('[[username]]',$this_email['volunteer']['username'], $msg);
			print "sending volunteer assignments email to $to\n";
			Mail($to, $subject, $msg, $from);
			//print $msg;
			//print "=========================================================";
		}
	}
	exit();
}

// VOLUNTEER GROUPS
$volunteer_groups = Array(
	'All Volunteers' => 15,
	'Worship Team' => 18,
	'AV Team' => 19,
	'KIDOPOLIS Volunteers' => 20,
	'KIDOPOLIS :: City' => 21,
	'KIDOPOLIS :: Village' => 22,
	'KIDOPOLIS :: Park' => 23,
	'KIDOPOLIS Registration' => 24,
	'KIDOPOLIS :: Backyard' => 33,
	'Welcome Team' => 25,
	'Volunteer Admin' => 26,
	'Follow-up Mentors' => 27,
	'Finance Team' => 32,
	'Life Groups Leaders' => 30,
	'Environment Team' => 42,
	'First Responders' => 53
);


// GENERAL SETUP
/************************************
*
* FUNCTIONS
*
************************************/

function validate_form($required_keys)
{
	// form validation
	$is_valid = True;
	foreach ($required_keys as $key)
	{
		$value = $_POST[$key];
		if (trim($value) === '') $is_valid = False;
	}
	return $is_valid;
}

function do_auth()
{
}

function nice_date($datetime)
{
	$timestamp = strtotime($datetime);
	return strftime('%b %e', $timestamp);
}
function tstamp($datetime)
{
	$timestamp = strtotime($datetime);
	return strftime('%Y%m%dT%H%M%S', $timestamp);
}
function a_get($array, $item, $default='')
{
	if (is_array($array) and array_key_exists($item, $array)) return $array[$item];
	else return $default;
}

function update_volunteers()
{
	global $_volunteer_db;
	global $crm;
	$volunteers = $crm->get_volunteers();


	// check to make sure volunteers are in local database
	foreach ($volunteers as $volunteer)
	{
		_debug($volunteer['display_name']);

		// CHECK TO SEE IF VOLUNTEER WITH THAT CONTACT ID ALREADY EXISTS
		if ( ! is_numeric($volunteer['contact_id'] )) continue;
		$query = sprintf("SELECT * FROM people WHERE id=%s ORDER BY people.name ASC", mysql_real_escape_string($volunteer['contact_id']));
		$person = $_volunteer_db->query($query);
		if (!$person)
		{
			_debug('adding new person');
			$id = mysql_real_escape_string($volunteer['contact_id']);
			$name = mysql_real_escape_string($volunteer['display_name']);
			$email = mysql_real_escape_string($volunteer['email']);
			$vusername = mysql_real_escape_string(str_replace(' ', '_', strtolower($volunteer['firstname'] . ' ' . $volunteer['lastname'])));
			if ($vusername == '_') $vusername = $email;
			$phone = mysql_real_escape_string($volunteer['phone']);
			$query = "INSERT INTO people SET id='$id', name='$name', email='$email', phone='$phone', username='$vusername'";
			$_volunteer_db->query($query);
		}
		else
		{
			_debug('updating person');
			$person = array(
				'id' => $volunteer['contact_id'],
				'name' => $volunteer['display_name'],
				'email' => a_get($volunteer,'email',''),
				'phone' => a_get($volunteer,'phone','')
				);
			$_volunteer_db->save('people',$person);
		}
	}
}

function update_volunteer($volunteer)
{
	global $_volunteer_db;
	_debug($volunteer);

	// CHECK TO SEE IF VOLUNTEER WITH THAT CONTACT ID ALREADY EXISTS
	if ( ! is_numeric($volunteer['contact_id'] )) return;
	$query = sprintf("SELECT * FROM people WHERE id=%s ORDER BY people.lastname ASC", mysql_real_escape_string($volunteer['contact_id']));
	$person = $_volunteer_db->query($query);
	if (!$person)
	{
		_debug('adding new person');
		$id = mysql_real_escape_string($volunteer['contact_id']);
		$name = mysql_real_escape_string($volunteer['display_name']);
		$lastname = mysql_real_escape_string($volunteer['last_name']);
		$firstname = mysql_real_escape_string($volunteer['first_name']);
		$email = mysql_real_escape_string($volunteer['email']);
		$vusername = strtolower(str_replace(' ', '_', $name));
		if ($vusername == '') $vusername = $name;
		$phone = mysql_real_escape_string($volunteer['phone']);
		$query = "INSERT INTO people SET id='$id', name='$name', lastname='$lastname', firstname='$firstname', email='$email', phone='$phone', username='$vusername'";
		$_volunteer_db->query($query);
	}
	else
	{
		_debug('updating person');
		$person = array(
			'id' => $volunteer['contact_id'],
			'name' => $volunteer['display_name'],
			'lastname' => $volunteer['last_name'],
			'firstname' => $volunteer['first_name'],
			'email' => a_get($volunteer,'email',''),
			'phone' => a_get($volunteer,'phone','')
			);
		$_volunteer_db->save('people',$person);
	}
	return $person;
}

function update_groups()
{
	global $volunteer_groups;
	global $_volunteer_db;
	global $crm;

	$volunteer_group_data = Array();

	// drop data in person_2_group table
	$query = "TRUNCATE TABLE person2group";
	$_volunteer_db->query($query);

	foreach($volunteer_groups as $name=>$gid)
	{
		// update db to reflect current group names
		$query = "REPLACE INTO groups SET id='$gid', name='$name'";
		$_volunteer_db->query($query);

		// go through group and update person_2_group table
		_debug('grabbing members for group: ' . $name . '(id: '. $gid . ')');
		$members = $crm->get_group_members($gid);
		_debug($members);
		foreach ($members as $person)
		{
			$person_id = $person['contact_id'];
			if ( ! $person_id) continue;
			update_volunteer($person);

			$query = "INSERT INTO person2group SET person_id='$person_id', group_id='$gid'";
			$_volunteer_db->query($query);
		}
	}
}

function _debug($s,$title = '', $force=false)
{
	global $show_debug;
	if (! $force and $_SESSION['lcc_volunteers_user']['permissions'] < 3) return;
	if (! $show_debug) return;
	print "<br />_debug: $title<br /><pre>";
	print_r($s);
	print "</pre>";
}

function force_debug($s,$title = '')
{
	print "<br />_debug: $title<br /><pre>";
	print_r($s);
	print "</pre>";
}

function dd($s)
{
    force_debug($s);
    die();
}

function time_overlaps($t1start, $t1end, $t2start, $t2end)
{
	// if t1 ends before t2 begins or if t2 ends before t1 begins there is no overlap
	if ($t1end <= $t2start OR $t2end <= $t1start) return FALSE;
	else return TRUE;
}

function is_working($person, $job)
{
	$jds = $job['datetime_start'];
	$jde = $job['datetime_end'];
	$jid = $job['jid'];
	foreach ($person['assignments'] as $assignment)
	{
		if ($assignment['jid'] == $jid) return "THIS JOB";
		if (time_overlaps($assignment['datetime_start'], $assignment['datetime_end'], $jds, $jde)) return $assignment['name'] . ' - ' . $assignment['note'];
	}
	return FALSE;
}

function is_off($person, $job)
{
	$jds = $job['datetime_start'];
	$jde = $job['datetime_end'];
	$jid = $job['jid'];
	foreach ($person['exclusions'] as $exclusion)
	{
		if (time_overlaps($exclusion['datetime_start'], $exclusion['datetime_end'], $jds, $jde)) return $exclusion['reason'] ? $exclusion['reason'] : 'REQUESTED OFF';
	}
	return FALSE;
}


// VIEW CONTROLLERS
function show_group_link($gid)
{
	global $_volunteer_db;
	$group = $_volunteer_db->get_group($gid);
	print "<a href=\"?action=show&object=group&id=${group['id']}\">" . $group['name'] . "</a>";
}

function show_ical_link()
{
	global $object;
	global $id;
	global $ical_token;
	$url = '?action=ical&token=' . $ical_token;
	if ($object) $url .= '&object='.$object;
	if($id) $url .= '&id=' . $id;
	print "\n\n" . '<div class="ical_links">import this page into your favorite calendar program';

	// ics file
	print "<a href=\"$url\" title=\"Copy this link to subscribe in your own calendar program.\" class=\"ical_link\">[ics file]</a>";

	// webcal protocol link
	print "<a href=\"webcal://${site_domain}/$url\" title=\"Click to subscribe in your calendar program.\" class=\"webcal_link\">iCal</a>";

	// google Calendar
	$url = "${site_url}/$url";
	$encoded_url = urlencode($url);
	$gcal_url = "http://www.google.com/calendar/render?cid=$encoded_url";
	print "<a href=\"$gcal_url\" title=\"Click to open in Google calendar.\" class=\"gcal_link\">Google</a>";

	print "</div> <!-- end ical_links -->\n\n";

}

function show_volunteer($pid, $details=FALSE)
{
	global $_volunteer_db;
	$person = $_volunteer_db->get_volunteer($pid);
	$groups = $_volunteer_db->get_groups_for_volunteer($pid);

	// begin output
	print "\n<li class=\"volunteer\">";
	show_person_link(array('pn' => $person['pn'], 'pid'=>$person['pid']),'View this volunteer');
	$civicrm_link = "http://lafayettecc.org/members/civicrm/contact/view?reset=1&cid=$pid";
	print "\n<p class='right'><small><a href=\"$civicrm_link\" target=\"DETAILS_WINDOW\" title=\"view in LCC Members Database\">[DETAILS]</a></small></p>";
	if ($details)
	{
		print "<ul class=\"details\">";
		print "\n<li>email: {$person['email']}";
		print "\n<li>phone: {$person['phone']}";
		print "</ul>";
	}

	print "\n<ul class='tags'>GROUPS:";
	foreach ($groups as $g)
	{
		print "\n<li class='tag'>";
		show_group_link($g['gid']);
		print "</li>";
	}
	print "\n</ul>\n</li>";
}

function show_volunteers()
{
	global $_volunteer_db;
	$volunteers_groups = $_volunteer_db->get_volunteers_and_groups();
	$simple_volunteers = Array();
	foreach ($volunteers_groups as $row)
	{
		$name = $row['pn'];
		$pid = $row['pid'];
		$gid = $row['gid'];
		$gn = $row['gn'];

		if (! array_key_exists($name, $simple_volunteers ))
		{
			$simple_volunteers[$name]['pid'] = $pid;
			$simple_volunteers[$name]['groups'] = Array();
		}
		$simple_volunteers[$name]['groups'][$gid] = $gn;
	}

	// begin output
	print "\n<ul>";

	foreach ($simple_volunteers as $name => $data)
	{
		print "\n<li>";
		show_person_link(array('pn' => $name, 'pid'=>$data['pid']));
		print "\n<br /><ul class='tags'>";
		foreach ($data['groups'] as $gid => $gn)
		{
			print "\n<li class='tag'>";
			show_group_link($gid);
			print "</li>";
		}
		print "\n</ul>\n</li>";
	}
}

function show_person_link($person, $title='')
{
	//_debug($person);
	$url = "?action=show&object=person&id=" . $person['pid'];
	print "<a href=\"$url\" title=\"$title\">$person[pn]</a>";
}

function show_unassign_link($pid,$jid, $html = '')
{
	$args = array('action' => 'unassign', 'object'=>'', 'id'=>'');
	if ( ! check_perms($args)) { print $html; return; }
	$url_args = array('action'=>'unassign', 'jid'=>$jid, 'pid'=>$pid);
	if (! $html ) $html = "&raquo;";
	make_link($url_args, $html,'remove this assignment', 'unassignment_link');
	//print "<a class='unassignment_link' href=\"{$url}\" title=\"remove this assignment\" >&raquo;</a>";
}

function show_assignment_link($person, $job, $html='')
{
	if (! is_array($person)) $person = array('pid' => $person, 'firstname' => 'this person');
	$pid = $person['pid'];

	if (! is_array($job)) $job = array('jid' => $job, 'name' => 'this job');
	$jid = $job['jid'];

	$args = array('action' => 'assign', 'object'=>'', 'id'=>'');
	if ( ! check_perms($args)) { print $html; return; }
	$url_args = array('action' => 'assign', 'jid' => $jid, 'pid' => $pid);
	if (! $html ) $html = "&laquo;";
	make_link($url_args, $html, "assign ${person['firstname']} to ${job['name']}", 'assignment_link');
}

function show_upcoming_jobs()
{
	global $_volunteer_db;

	print "\n<div class='upcoming_jobs'>\n";

	$jobs = $_volunteer_db->get_upcoming_jobs();
	foreach($jobs as $job)
	{
		//show_job($job['jid']);
		show_job($job);
	}
	print "\n</div> <!-- upcoming_jobs -->\n";
}

function get_all_upcoming()
{
	global $_volunteer_db;

	// grab all upcoming jobs sorted by default_group
	$q = "select jobs.*, groups.name as group_name from jobs inner join groups on jobs.default_group=groups.id where jobs.datetime_start >= NOW()";
	$jobs = $_volunteer_db->query($q);

	// grab all assignments with people names
	$q = "select people.id as pid, people.firstname, people.lastname, person2job.job_id as jid from people inner join person2job on person2job.person_id=people.id";
	$assignments = $_volunteer_db->query($q);

    // grab all upcoming conflicting exclusions
    $q = 'select people.id as pid, jobs.id as jid, exclusions.id as eid, exclusions.reason, jobs.*, exclusions.*
            from people
            join jobs
            inner join person2job
            on people.id=person2job.person_id
            inner join exclusions
            on people.id=exclusions.person_id

            WHERE
            jobs.datetime_start > now() AND
            (
            (exclusions.datetime_start < jobs.datetime_start AND exclusions.datetime_end > jobs.datetime_end)
            OR
            (exclusions.datetime_start > jobs.datetime_start AND exclusions.datetime_start < jobs.datetime_end)
            OR
            (exclusions.datetime_end > jobs.datetime_start AND exclusions.datetime_end < jobs.datetime_end)
            )';
    $exclusions = $_volunteer_db->query($q);
    $exclusions_by_job = Array();
    foreach ($exclusions as $row)
    {
        $eid = $row['eid'];
        $jid = $row['jid'];
        $pid = $row['pid'];
        $reason = $row['reason'];
        if (!isset($exclusions_by_job[$jid])) $exclusions_by_job[$jid] = Array();
        if (!isset($exclusions_by_job[$jid][$pid])) $exclusions_by_job[$jid][$pid] = Array('reason' => $reason);
    }

	// grab all upcoming conflicting jobs
	$q = "SELECT people.id as pid, people.name as pn
			FROM people
			WHERE people.id = person2group.person_id AND person2group.group_id = {$gid} AND people.id NOT IN (SELECT person_id FROM person2job WHERE person2job.job_id = {$jid}) ORDER BY people.lastname ASC";
    $q = 'select people.id as pid, jobs.id as jid, jobs.name, exclusions.reason, jobs.*, exclusions.*
            from people
            join jobs
            inner join person2job
            on people.id=person2job.person_id
            inner join exclusions
            on people.id=exclusions.person_id

            WHERE
            jobs.datetime_start > now() AND
            (
            (exclusions.datetime_start < jobs.datetime_start AND exclusions.datetime_end > jobs.datetime_end)
            OR
            (exclusions.datetime_start > jobs.datetime_start AND exclusions.datetime_start < jobs.datetime_end)
            OR
            (exclusions.datetime_end > jobs.datetime_start AND exclusions.datetime_end < jobs.datetime_end)
            )';


	return Array('jobs' => $jobs, 'assignments' => $assignments, 'conflicts'=>$exclusions);

	// call a chartmaker with those three results directly
}

function get_all_upcoming_by_group($group_id = '')
{
    global $_volunteer_db;

	// grab all groups and the related jobs
	if ($group_id)
		$q = "select jobs.id as jid, jobs.*, groups.id as gid, groups.name as group_name from jobs inner join groups on jobs.default_group=groups.id where jobs.datetime_start >= SUBDATE(NOW(),1) AND jobs.default_group=${group_id} ORDER BY jobs.datetime_start ASC";
	else
		$q = "select jobs.id as jid, jobs.*, groups.id as gid, groups.name as group_name from jobs inner join groups on jobs.default_group=groups.id where jobs.datetime_start >= SUBDATE(NOW(),1) ORDER BY jobs.datetime_start ASC";
	$result = $_volunteer_db->query($q);
	$groups = Array();
	foreach ($result as $row)
	{
		$gid = $row['gid'];
		$jid = $row['jid'];
		if (!isset($groups[$gid])) $groups[$gid] = Array('gid' => $gid, 'name' => $row['group_name'], 'jobs' => array(), 'people' => array());
		$groups[$gid]['jobs'][$jid] = Array('jid' => $jid, 'description' => $row['description'], 'datetime_start' => $row['datetime_start'], 'datetime_end' => $row['datetime_end'], 'name' => $row['name']);
	}

    // grab all groups and related people
	if ($group_id)
	    $q = "select people.id as pid, people.firstname, people.lastname, person2group.group_id as gid
	            from people
	            inner join person2group
	            on people.id=person2group.person_id
				where person2group.group_id = ${group_id}";
	else
	    $q = "select people.id as pid, people.firstname, people.lastname, person2group.group_id as gid
	            from people
	            inner join person2group
	            on people.id=person2group.person_id";
    $results = $_volunteer_db->query($q);
    foreach ($results as $row)
    {
        $gid = $row['gid'];
        $pid = $row['pid'];
		// if a group id doesn't exist in the groups array, it's because this group has no jobs, and we should ignore it for now.
		if (!isset($groups[$gid])) continue;
        if (!isset($groups[$gid]['people'][$pid]))
        {
            $groups[$gid]['people'][$pid] = Array();
			$groups[$gid]['people'][$pid]['pid'] = $pid;
            $groups[$gid]['people'][$pid]['firstname'] = $row['firstname'];
            $groups[$gid]['people'][$pid]['lastname'] = $row['lastname'];
        }
    }

	// NOW THE GROUPS ARE POPULATED WITH PEOPLE AND JOBS
	// CREATE CONFLICT INFORMATION

	// grab all job assignments with people ids
	$q = "select people.id as pid, person2job.job_id as jid, jobs.name as jn, person2job.note as note, jobs.datetime_start, jobs.datetime_end from people inner join person2job on person2job.person_id=people.id inner join jobs on jobs.id=person2job.job_id";
	$result = $_volunteer_db->query($q);
	$assignments = Array();
	foreach ($result as $row)
	{
		$pid = $row['pid'];
		$jid = $row['jid'];
		if (!isset($assignments[$pid])) $assignments[$pid] = Array();
		$assignments[$pid][$jid] = array('jid' => $jid, 'name' => $row['jn'], 'note' => $row['note'], 'datetime_start' => $row['datetime_start'], 'datetime_end' => $row['datetime_end']);
	}

	// grab all exclusions with people ids
	$q = "select people.id as pid, exclusions.reason, exclusions.* from people, exclusions where people.id=exclusions.person_id";
	$result = $_volunteer_db->query($q);
	$exclusions = Array();
	foreach ($result as $row)
	{
		$pid = $row['pid'];
		$jid = $row['jid'];
		if (!isset($exclusions[$pid])) $exclusions[$pid] = Array();
		$exclusions[$pid][] = array('reason' => $row['reason'], 'datetime_start' => $row['datetime_start'], 'datetime_end' => $row['datetime_end']);
	}



    // order the data into chart friendly format
    foreach ($groups as $gid => $group)
    {
		foreach ($group['people'] as $pid => $person)
		{
			$groups[$gid]['people'][$pid]['exclusions'] = $exclusions[$pid];
			$groups[$gid]['people'][$pid]['assignments'] = $assignments[$pid];
		}
    }

	if ($group_id) return $groups[$group_id];
	else return $groups;
}

function show_group_chart($group_data)
{
    $chart_title = $group_data['name'] . ' | Jobs Chart';
    ?>
	<h2><?php print $chart_title; ?></h2>
	<small>If you have appropriate permissions, you may click in any cell to add or remove an assignment.</small>
	<div class="job_chart">
		<div class="job_chart_main">
			<table class="job_chart <?php print $classes; ?>">
				<thead>
					<tr>
						<th class="first_column">&nbsp;</th>
						<?php foreach ($group_data['jobs'] as $jid => $job): ?>
							<th>
								<a href="?action=show&object=job&id=<?php echo $jid; ?>">
								<?php print nice_date($job['datetime_start']); ?><br /><?php echo $job['name']; ?>
								</a>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($group_data['people'] as $pid => $person): ?>
					<tr>
						<td class="first_column">
							<a href="?action=show&object=person&id=<?php echo $pid; ?>">
							<?php echo $person['firstname'] . ' ' . $person['lastname'] ; ?>
							</a>
						</td>
						<?php foreach ($group_data['jobs'] as $jid => $job): ?>
							<?php
                                if (isset($person['assignments'][$jid]))
                                {
                                    $note = $person['assignments'][$jid]['note'];
                                    $status = 'assigned';
                                }
                                elseif ($reason = is_working($person, $job))
                                {
                                    $note = $reason;
                                    $status = 'busy';
                                }
                                elseif ($reason = is_off($person, $job))
                                {
                                    $note = $reason;
                                    $status = 'off';
                                }
                                else
                                {
                                    $status = 'available';
                                }
                            ?>
							<?php if ($status == 'available'): ?>

							<td class="available">
								<?php show_assignment_link($person, $job, 'Assign ' . $person['firstname'] ); ?>
							</td>

							<?php elseif ($status == 'assigned'): ?>

							<td class="assigned">
								<?php show_unassign_link($pid, $jid, $note); ?>
							</td>

							<?php else: ?>

							<td class="<?php echo $status; ?>" title="<?php echo $note; ?>">
								<small><?php echo $note; ?></small>
							</td>

							<?php endif; ?>
						<?php endforeach; ?>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
    <?php

}


function show_chart($object, $id, $classes='')
{
    /*
        TO Improve performance, we need to consolidate queries
        craft one query to get:
            all jobs
            all groups
            all people
            all assignments
            [group_data, job_data, person_data, assignment_data]
        then, restructure results to get a multi-dimensional array
    */
	global $_volunteer_db;
	if ($object == 'group')
	{
		$group = $_volunteer_db->get_group($id);
		$people = $_volunteer_db->get_volunteers_for_group($id);
		$jobs = $_volunteer_db->get_upcoming_jobs_for_group($id);
		$chart_title = $group['name'] . " | Job Chart";
	}
	if ($object == 'person')
	{
		$person = $_volunteer_db->get_volunteer($id);
		$people = Array($person);
		$jobs = $_volunteer_db->get_upcoming_jobs_for_person($id, True);
		$chart_title = $person['fullname'] . " | Job Chart";
	}

	if (!is_array($jobs)) return;
	if (count($jobs) < 1) return;

	// JOBS ACROSS TOP
	// PEOPLE ON LEFT
	// RED BOX FOR TIME OFF
	// GREEN BOX FOR ASSIGNMENT
	// MAKE ASSIGNMENTS CLICKABLE
	// MAKE FIRST COLUMN "FROZEN"

	//FIRST, CREATE TWO DIMENSIONAL ARRAY.

	$chart_data = Array(Array());
	foreach ($jobs as $job)
	{
		$assignments = $_volunteer_db->get_volunteers_for_job($job['jid']);
		foreach ($assignments as $assignment)
		{
			$chart_data[$job['jid']][$assignment['pid']] = Array('status' => 'assigned', 'note' => $assignment['note']);
		}
		$volunteers = $_volunteer_db->get_available_volunteers($job['jid']);

		foreach ($volunteers['available'] as $volunteer)
		{
			$chart_data[$job['jid']][$volunteer['pid']] = Array('status' => 'available');
		}
		foreach ($volunteers['busy'] as $volunteer)
		{
			$chart_data[$job['jid']][$volunteer['volunteer']['pid']] = Array('status' => 'busy', 'note' => $volunteer['reason']);
		}
	}
    display_chart($chart_title, $jobs, $people, $chart_data, $classes);
}

function display_chart($chart_title, $jobs, $people, $chart_data, $classes)
{
    ?>
	<h2><?php print $chart_title; ?></h2>
	<small>If you have appropriate permissions, you may click in any cell to add or remove an assignment.</small>
	<div class="job_chart">
		<div class="job_chart_main">
			<table class="job_chart <?php print $classes; ?>">
				<thead>
					<tr>
						<th class="first_column">&nbsp;</th>
						<?php foreach ($jobs as $job): ?>
							<th>
								<a href="?action=show&object=job&id=<?php echo $job['jid']; ?>">
								<?php print nice_date($job['datetime_start']); ?><br /><?php echo $job['jn']; ?>
								</a>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($people as $person): ?>
					<tr>
						<td class="first_column">
							<a href="?action=show&object=person&id=<?php echo $person['pid']; ?>">
							<?php echo $person['pn']; ?>
							</a>
						</td>
						<?php foreach ($jobs as $job): ?>
							<?php $data = $chart_data[$job['jid']][$person['pid']]; ?>
							<?php if ($data['status'] == 'available'): ?>

							<td class="available">
								<?php show_assignment_link($person, $job, '&bull;'); ?>
							</td>

							<?php elseif ($data['status'] == 'assigned'): ?>

							<td class="assigned">
								<?php show_unassign_link($person['pid'], $job['jid'], $data['note']); ?>
							</td>

							<?php else: ?>

							<td class="busy" title="<?php echo $data['note']; ?>">
								<small><?php echo $data['note']; ?></small>
							</td>

							<?php endif; ?>
						<?php endforeach; ?>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php /* overlay table code

		<div class="job_chart_overlay">
			<!-- job_chart_overlay to make the "People" column fixed on the left -->
			<table class="job_chart job_chart_overlay <?php print $classes; ?>">
				<tr>
					<th class="first_column">&nbsp;</th>
					<?php foreach ($jobs as $job): ?>
						<th>
							<a href="?action=show&object=job&id=<?php echo $job['jid']; ?>">
							<?php print nice_date($job['datetime_start']); ?><br /><?php echo $job['jn']; ?>
							</a>
						</th>
					<?php endforeach; ?>
				</tr>
				<?php foreach ($people as $person): ?>
				<tr>
					<td class="first_column">
						<a href="?action=show&object=person&id=<?php echo $person['pid']; ?>">
						<?php echo $person['pn']; ?>
						</a>
					</td>
					<?php foreach ($jobs as $job): ?>
						<?php $data = $chart_data[$job['jid']][$person['pid']]; ?>
						<?php if ($data['status'] == 'available'): ?>

						<td class="available">
							<?php show_assignment_link($person['pid'], $job['jid'], '&bull;'); ?>
						</td>

						<?php elseif ($data['status'] == 'assigned'): ?>

						<td class="assigned">
							<?php show_unassign_link($person['pid'], $job['jid'], $data['note']); ?>
						</td>

						<?php else: ?>

						<td class="busy" title="<?php echo $data['note']; ?>">
							<small><?php echo $data['note']; ?></small>
						</td>

						<?php endif; ?>
					<?php endforeach; ?>
				</tr>
				<?php endforeach; ?>
			</table>
		</div>
		*/ ?>


	</div>
	<?php
}

function show_group($group_id)
{
	$group_data = get_all_upcoming_by_group($group_id);
	$people = $group_data['people'];
	$jobs = $group_data['jobs'];
	?>
	<h1><?php print $group['name']; ?></h1>
	<?php show_group_chart($group_data); ?>

	<div class="col1">
		<h2>Upcoming Jobs</h2>
		<?php foreach ($jobs as $job): ?>
		<?php show_job($job); ?>
		<?php endforeach; ?>
	</div>
	<div class="col2">
		<h2>People</h2>
		<?php foreach ($people as $person): ?>
		<?php show_volunteer($person['pid'], $details=TRUE); ?>
		<?php endforeach; ?>
	</div>

	<?php
}


function old_show_group($group)
{
	global $_volunteer_db;
	if (!is_array($group))
	{
		$group = $_volunteer_db->get_group($group);
	}
	$id = $group['id'];
	$people = $_volunteer_db->get_volunteers_for_group($id);
	$jobs = $_volunteer_db->get_upcoming_jobs();
	?>
	<h1><?php print $group['name']; ?></h1>
	<?php show_chart('group',$id); ?>

	<div class="col1">
		<h2>Upcoming Jobs</h2>
		<?php foreach ($jobs as $job): ?>
		<?php if ($job['default_group'] == $id) show_job($job); ?>
		<?php endforeach; ?>
	</div>
	<div class="col2">
		<h2>People</h2>
		<?php foreach ($people as $person): ?>
		<?php show_volunteer($person['pid'], $details=TRUE); ?>
		<?php endforeach; ?>
	</div>

	<?php
}

function show_job($job, $link_action='show')
{
	global $_volunteer_db;

	if (!is_array($job))
	{
		//assume $job is actually just an id and grab $job from database
		$job = $_volunteer_db->get_job($job);
	}
	$id = $job['jid'];
	$people = $_volunteer_db->get_volunteers_for_job($id);
	$default_group = $_volunteer_db->get_group($job['default_group']);

	?>

	<div class="jobgroup"><a href="?action=show&object=group&id=<?php print $default_group['id']; ?>"><?php print $default_group['name']; ?></a></div>
	<h3 class="jobtitle"><?php print nice_date($job['datetime_start']); ?> :: <a href="?action=<?php echo $link_action; ?>&object=job&id=<?php print $job['jid']; ?>"><?php print $job['name']; ?></a><span class="datetime"><?php print $job['datetime_start']; ?></span> &mdash; <span class="datetime"><?php print $job['datetime_end']; ?></span></h3>
	<div class="jobdescription">
	<?php print $job['description']; ?>

	<ul class="tags">
	<?php foreach ($people as $person): ?>

	<li class="tag"><?php show_person_link($person,$person['note']); ?> <?php show_unassign_link($person['pid'], $job['id']); ?></li>

	<?php endforeach; ?>
	</ul>
	</div> <!-- jobdescription -->
	<?php
}

function show_available_volunteers($jid)
{
	// first, get the job to learn the date, and the default team
	global $_volunteer_db;
	$volunteers = $_volunteer_db->get_available_volunteers($jid);

	print "\n<ul class='available'>";
	foreach ($volunteers['available'] as $volunteer)
	{
		show_assignment_link($volunteer, $jid);
		show_volunteer($volunteer['pid']);
		print "<div class='clear'></div>";
	}
	print "\n</ul>";

	print "\n<h2>Busy</h2>";
	print "\n<ul class='busy'>";
	foreach ($volunteers['busy'] as $volunteer)
	{
		show_volunteer($volunteer['volunteer']['pid']);
		print "<div class='reason'>CONFLICT: " . $volunteer['reason'] . "</div>";
	}
	print "\n</ul>";
}

function show_jobs_for_volunteer($pid, $upcoming_only=TRUE)
{
	global $_volunteer_db;
	$jobs = $_volunteer_db->get_upcoming_jobs_for_person($pid, $upcoming_only);
	foreach ($jobs as $job)
	{
		show_job($job['jid']);
	}
}

function show_exclusions_for_volunteer($pid)
{
	global $_volunteer_db;
	$exclusions = $_volunteer_db->get_exclusions_for_volunteer($pid);

	?>
	<?php foreach ($exclusions as $exclusion): ?>

		<div class="block">
		<h3><a href="?action=show&object=exclusion&id=<?php print $exclusion['id']; ?>">START: <?php print $exclusion['eds']; ?><br />END: <?php print $exclusion['ede']; ?></a></h3>
		<p><?php print $exclusion['reason']; ?></p>
		</div>

	<?php endforeach; ?>
	<?php
}

function show_exclusion($eid)
{
	global $_volunteer_db;
	$exclusion = $_volunteer_db->get_exclusion($eid);
	?>

		<div class="block">
		<h3><a href="?action=show&object=exclusion&id=<?php print $eid; ?>">START: <?php print $exclusion['eds']; ?><br />END: <?php print $exclusion['ede']; ?></a></h3>
		<p><?php print $exclusion['reason']; ?></p>
		</div>


	<?php
}

function show_job_form($jid)
{
	global $_volunteer_db;
	$job = $_volunteer_db->get_job($jid);
	$groups = $_volunteer_db->get_groups();
	?>

	<form action="" method="POST">
	<?php if (!$jid) : ?>
	<input type="hidden" name='save_as_new' id='save_as_new' value='1' />
	<?php endif; ?>
	<input type="hidden" name='id' id='id' value="<?php print $job['id']; ?>" />

	<script type="text/javascript">
	$(function() {
		$("#datetime_start").AnyTime_picker( {format: '%Y-%m-%d %H:%i:%s' , askSecond: true});
		$("#datetime_end").AnyTime_picker({format: '%Y-%m-%d %H:%i:%s' , askSecond: true});
	});

	function update_job_end()
	{
		var ds = new Date(document.getElementById('datetime_start').value);
		var duration_minutes = parseInt(document.getElementById('job_duration').value);
		if (duration_minutes == 'NaN') duration_minutes = 70;
		end_time = (ds.getTime() + (duration_minutes * 60 * 1000));
		//alert(end_time);
		var de = new Date(end_time);
		var date_str=de.getFullYear() + '-' + ('0' + (de.getMonth() + 1)).substr(-2,2) + '-' + ('0' + de.getDate()).substr(-2,2) + ' ' +('0'+de.getHours()).substr(-2,2)+':'+('0'+de.getMinutes()).substr(-2,2) + ":00";
		//alert(date_str);
		document.getElementById('datetime_end').value = date_str;
	}

	function update_job_duration()
	{
		var ds = new Date(document.getElementById('datetime_start').value);
		var de = new Date(document.getElementById('datetime_end').value);
		delta = de.getTime() - ds.getTime();
		if (delta < 0)
		{
			alert('Job duration can\'t be a negative value. Doublecheck your dates.')
			delta = 0;
		}
		one_minute = 1000 * 60;
		duration_minutes = Math.ceil(delta/one_minute);
		if (isNaN(duration_minutes)) duration_minutes = '0';
		document.getElementById('job_duration').value = duration_minutes;
	}
	</script>

	<div>All fields are required.</div>
	<table>
		<tr>
			<td>job name:</td>
			<td><input type="text" name='name' id='name' value="<?php print $job['name']; ?>" /></td>
		</tr>
		<tr>
			<td>start time:</td>
			<td>
				<input type="text" name='datetime_start' id='datetime_start' value="<?php print $job['datetime_start']; ?>" onblur="update_job_end();" />
				<a class="button blue" onclick="document.getElementById('datetime_start').value='';return false;">clear</a>
			</td>
		</tr>
		<tr>
			<td>end time:</td>
			<td>
				<input type="text" name='datetime_end' id='datetime_end' value="<?php print $job['datetime_end']; ?>" onchange="update_job_duration();" />
				<a class="button blue" onclick="document.getElementById('datetime_end').value='';return false;">clear</a>
			</td>
		</tr>
		<tr>
			<td>job duration (minutes):<br /><small>You can change the job end time by changing this field.</small></td>
			<td><input type="text" name='job_duration' id='job_duration' value="" onblur="update_job_end();" /></td>
		</tr>
		<tr><td>description:</td><td><textarea name='description' id='description'><?php print $job['description']; ?></textarea></td></tr>
		<tr><td>default group:</td><td>
		<select name="default_group" id="default_group">
			<?php foreach ($groups as $group) : ?>
			<option value="<?php print $group['id'];?>" <?php if ($group['id'] == $job['default_group']) print "SELECTED='SELECTED'"; ?>><?php print $group['name']; ?></option>
			<?php endforeach; ?>
		</select>
		</td></tr>
		<tr style="height: 100px;">
			<td>Create Duplicates:</td>
			<td style="vertical-align: middle;">
				<div style="float:right;width:300px;margin: 0 2px;"><small>You can create additional copies of this job in the same time slot each week by entering a number here.</small></div>
				<input style="position:relative; top:5px;" name="repeat" value="" id="repeat" />
			</td>
		</tr>
		<tr><td>Other Options:</td><td>
			<input type="checkbox" name='is_template' id='is_template' <?php if ($job['is_template']) print "checked=\"checked\""; ?> value='1' />This is a job Template.
			<?php if ($jid) : ?>
			<input type="checkbox" name='save_as_new' id='save_as_new' value='1' />save as a new job
			<?php endif; ?>
		</td></tr>
	</table>

	<hr />

	<input class="button green" type="submit" name="submit" value="submit" />
	<input class="button redbutton" type="submit" name="submit" value="DELETE" />
	</form>

	<script type="text/javascript">
	$(document).ready(function() {update_job_duration()});
	</script>

	<?php
}

function show_job_templates()
{
	// get jobs
	global $_volunteer_db;
	$q = "SELECT *, id AS jid FROM jobs WHERE is_template='1'";
	$templates = $_volunteer_db->query($q);
	?>

	<ul>
		<?php foreach ($templates as $template) show_job($template,'edit'); ?>
	</ul>


	<?php
}

function show_volunteer_form($pid, $details=TRUE)
{
	global $_volunteer_db;
	$volunteer = $_volunteer_db->get_volunteer($pid);
	?>

	<form action="" method="POST" onsubmit="if (document.getElementById('password').value != document.getElementById('password_confirm').value) {alert('Passwords don\'t match'); return false;}">

	<label for="username">username: </label><input id="username" name="username" value="<?php print $volunteer['username']; ?>" /><br />
	<label for="password">password: </label><input id="password" type="password" name="password" value="<?php print $volunteer['password']; ?>" /><br />
	<label for="password_confirm">confirm: </label><input id="password_confirm" type="password" name="password_confirm" value="<?php print $volunteer['password']; ?>" /><br />
	<input type="submit" name='submit' id='submit' value="submit" />
	</form>

	<?php
}

function show_exclusion_form($eid)
{
	global $_volunteer_db;
	$exclusion = $_volunteer_db->get_exclusion($eid);
	$vuser = $_SESSION['lcc_volunteers_user'];
	//_debug($_SESSION);
	if ($vuser['permissions'] > 2)
		$volunteers = $_volunteer_db->get_volunteers();
	else
	{
		$volunteers = Array($vuser);
		$volunteers[0]['pid'] = $vuser['id'];
		$volunteers[0]['pn'] = $vuser['name'];
	}
	//_debug($volunteers[0]);
	?>

	<form action="" method="POST">
	<p>Defaults to three Sundays from now.<br /><strong>Be sure to check the hours and minutes too!</strong></p>
	<?php if (!$eid) : ?>
	<input type="hidden" name='save_as_new' id='save_as_new' value='1' />
	<input type="hidden" name='id' id='id' value="" />
	<?php else: ?>
	<input type="hidden" name='id' id='id' value="<?php print $eid; ?>" />
	<?php endif; ?>

	<script type="text/javascript">
	$(function() {
		$("#datetime_start").AnyTime_picker( {format: '%Y-%m-%d %H:%i:%s' , askSecond: false});
		$("#datetime_end").AnyTime_picker({format: '%Y-%m-%d %H:%i:%s' , askSecond: false});
	});
	</script>

	<?php

	//guess the start_time for an exclusion to be this coming Sunday at midnight
	//guess the end_time for this exclusion to be this coming Sunday at 23:59:59
	if (! $exclusion['datetime_start'] )
	{
		$today = date();
		$wday = date('w');
		//_debug($wday);
		$day_start = mktime(0,0,0,date('n'), date('j'), date('Y'));
		$day_end = mktime(23,59,59,date('n'), date('j'), date('Y'));
		$interval = 21 - $wday;
		$sunday_morning = $day_start + (24*$interval*60*60);
		$sunday_night = $day_end + (24*$interval*60*60);
		$exclusion['datetime_start'] = strftime("%Y-%m-%d %H:%M", $sunday_morning);
		$exclusion['datetime_end'] = strftime("%Y-%m-%d %H:%M", $sunday_night);
	}

	?>
	<table>
		<tr><td>start time:</td><td><input name='datetime_start' id='datetime_start' value="<?php print $exclusion['datetime_start']; ?>" /><button onclick="document.getElementById('datetime_start').value='<?php print $exclusion['datetime_start']; ?>';return false;">reset</button></td></tr>
		<tr><td>end time:</td><td><input name='datetime_end' id='datetime_end' value="<?php print $exclusion['datetime_end']; ?>" /><button onclick="document.getElementById('datetime_end').value='<?php print $exclusion['datetime_end']; ?>';return false;">reset</button></td></tr>
		<tr><td>reason:</td><td><textarea name='reason' id='reason'><?php print $exclusion['reason']; ?></textarea></td></tr>
		<?php if ($eid) : ?>

		<tr><td>options:</td><td>
			<input type="checkbox" name='save_as_new' id='save_as_new' value='1' />save as a new exclusion
		</td></tr>

		<?php endif; ?>
		<tr><td>record for:</td><td>
		<select name="person_id" id="person_id">
			<?php foreach ($volunteers as $volunteer) : ?>
			<option value="<?php print $volunteer['id'];?>" <?php if ($volunteer['id'] == $exclusion['person_id']) print "SELECTED='SELECTED'"; ?>><?php print $volunteer['name']; ?></option>
			<?php endforeach; ?>
		</select>
	</td></tr>
	</table>
	<input type="submit" name="submit" value="submit" />
	<input type="submit" name="submit" value="DELETE" />
	</form>

	<?php
}

function assign_job_to_volunteer($jid, $pid)
{
	if (!isset($_POST['note']))
	{
		?>
		<!DOCTYPE html>
		<html>
		<head>
		<style type="text/css">
		body { font-family: "Century Gothic", Futura, "Segoe UI", Calibri, sans-serif; width:960px;margin:auto;font-size: 15px;padding:0;}
		input {width: 60%; padding: 5px; font-weight: bold;font-size: 24pt;margin:10px;}
		table {border:0; padding: 0;}
		label {width: 300px;display: block;margin-top: 20px;}
		</style>
		<body>
		<form method='POST'>
		<label for="note">Add a note to this assignment:</label>
		<input id='note' name='note' style='width: 750px;' />
		<input type='submit' value='submit' style='width:150px;'/>
		<input type="hidden" name="return_to" value="<?php echo $_SERVER['HTTP_REFERER'];?>" />
		</form>
		<script>
			document.getElementById('note').focus();
		</script>
		</body>
		</html>

		<?php
		exit();
	}
	global $_volunteer_db;
	$note = mysql_real_escape_string($_POST['note']);

	$q = "INSERT INTO person2job SET person_id='{$pid}',  job_id='{$jid}', note='{$note}'";
	$_volunteer_db->query($q);

	$url = $_POST['return_to'];
	if (! $url) $url = "?action=show&object=job&id={$jid}";
	Header("Location: {$url}");
	exit();
}

function delete_assignment($jid, $pid)
{
	global $_volunteer_db;
	$q = "DELETE FROM person2job WHERE person_id={$pid} AND job_id={$jid}";
	$_volunteer_db->query($q);
	$url = $_SERVER['HTTP_REFERER'];
	//$url = "?action=show&object=job&id={$jid}";
	Header("Location: {$url}");
}

function delete_job($jid)
{
}

function delete_exclusion($eid)
{
}


function show_error()
{
	print "<div class=\"alert\">There was an error in your request. Please click the 'Home' link and try again.</div>";
}

function make_link($args, $text="", $title="", $class ='')
{
	$url = '';
	foreach ($args as $key=>$value) $url .= "{$key}={$value}&";
	if (check_perms($args)) print "<a class='{$class}' href=\"?{$url}\" title=\"{$title}\">{$text}</a>";
}

function show_edit_link()
{
	global $object;
	global $id;
	$args = array('object' => $object, 'action' => 'edit', 'id' => $id);
	if (check_perms($args)) :

	?>
	<a href="?object=<?php print $object; ?>&action=edit&id=<?php print $id; ?>">[[ EDIT ]]</a>
	<?php

	endif;
}

function check_perms($args = '')
{
	if ($args == '') return FALSE;

	global $acl;
	global $ical_token;
	global $vuser;
	//$vuser = $_SESSION['lcc_volunteers_user'];
	$action = a_get($args,'action');
	$object = a_get($args,'object');
	$id = a_get($args,'id');
	$perms = a_get($acl,$action,array());
	$min_perm = a_get($perms,$object,a_get($perms,'default',1));
	if ($vuser['permissions'] >= $min_perm) return TRUE;
	if($action=='edit' and $object=='person' and $id == $vuser['id']) return TRUE;
	return FALSE;
}

function authenticate()
{
	global $_volunteer_db;
	global $volunteer_api_key;
	global $ical_token;
	global $vuser;
    global $user;


    // check to see if login can be bypassed by calendar token
	if (isset($_REQUEST['token']) && $_REQUEST['token'] == $ical_token)
	{
		$vuser['id'] = 0;
		$vuser['permissions'] = 0;
		$vuser['name'] = 'api';
		return $vuser;
	}

    // check to see if login can be bypassed because of api key
	if (isset($_REQUEST['key']) && $_REQUEST['key'] == $volunteer_api_key)
	{
		$vuser['id'] = 0;
		$vuser['permissions'] = 0;
		$vuser['name'] = 'api';
		return $vuser;
	}

    // check to see if the login form was submitted
	if (isset($_POST['login_submit']))
	{
        // check to see if the 'forgot password' form was submitted
		if (isset($_POST['forgot']))
		{
			$email = $_POST['username'];
			$vuser = $_volunteer_db->get_person_by_email($email);
			$pid = $vuser['id'];
			if (! $vuser )
			{
				$msg = "No one with that email address [[  $email  ]] is in our volunteer database.";
			}
			else
			{
				$newpass = array('jesuslovesme','thisiknow','forthebible','tellsmeso','inthebeginning','thetimeiscomingandhasnowcome','spiritandintruth');
				$newpass = $newpass[array_rand($newpass)];
				$hash = md5($newpass);
				$q = "UPDATE people SET password='{$hash}' WHERE people.id={$pid}";
				//_debug($q);
				$_volunteer_db->query($q);
				$from = "FROM: \"Pastor Jeff\" <pastor@lafayettecc.org>";

				mail($vuser['email'], "LCC VOLUNTEERS password reset", "Your password has been reset. Here are your login details: \n\n\tusername: ${user['username']}\n\tpassword: $newpass\n\nPlease login at ${site_url} to change it right away.", $from);

				mail('pastor@lafayettecc.org', "LCC VOLUNTEERS password reset", "A user with the email address of ${user['email']} has requested a password reset on the LCC Volunteers site. If they have problems, the new temporary login details are here: \n\n\tusername: ${user['username']}\n\tpassword: $newpass\n\n", $from);

				$msg = "Your new password has been emailed to you.";
				unset($vuser);
			}
		}
		else
		{
            // if we made it here, the normal login form was submitted
            // let's go ahead and authenticate
			$vusername = $_POST['username'];
			$password = $_POST['password'];

            // old code was to grab the user from our own database
			//$vuser = $_volunteer_db->get_person_by_credentials($vusername, $password);

            // new code is to authenticate them from the drupal backend.
            if ($uid = user_authenticate($vusername,$password))
            {
                // perform actual drupal authentication
                $user = user_load($uid);
                user_login_finalize();
            }
            else
			{
                unset($vuser);
				$msg = 'Username and Password incorrect. Could not log you in.';
			}
		}
	}

    // check to see if user has authenticated by drupal
    if (isset($user->uid) && $user->uid > 0)
    {
        // user is logged in through drupal
//        $params = array(
//            'version' => 3,
//            'uf_id' => $uid,
//            'sequential' => 1,
//        );
//        $result = civicrm_api('UFMatch', 'getsingle', $params);
//        $user->contact_id = $result['contact_id'];
        $contact_id = $_SESSION['CiviCRM']['userID'];
        $vuser = $_volunteer_db->get_person_by_id($contact_id);

        // the user permissions are stored in the
        // custom database as a field labeled "permissions"
        // but they can also be elevated automatically
        // if the user is in the "Volunteer Admin" group

        // we check that now. The Volunteer Admin group is #26
        $groups = $_volunteer_db->get_groups_for_volunteer($vuser['id']);
        $gids = array_keys($groups);
        if ( array_search (26, $gids) and ($vuser['permissions'] < 2 ) ) $vuser['permissions'] = 2;

    }

    // all authentication methods have been processed.
    // now we modify the session variables for our system
	if (isset($vuser['permissions']))
        $_SESSION['lcc_volunteers_user'] = $vuser;

	if (isset($_SESSION['lcc_volunteers_user']['permissions']))
        return $_SESSION['lcc_volunteers_user'];

	else
	{
		?>
		<!DOCTYPE html>
		<html>
		<head>
		<style type="text/css">
		body { font-family: "Century Gothic", Futura, "Segoe UI", Calibri, sans-serif; width:960px;margin:auto;font-size: 15px;}
		input[type=text], input[type=password], input[type=submit] {width: 576px; padding: 5px; font-weight: bold;font-size: 24pt;}
		input[type=submit] {padding: 10px; width: 590px;margin-top: 20px;}
		table {border:0; padding: 0;}
		label {width: 300px;display: block;margin-top: 20px;}
		.alert {width: 100%; border: 1px solid red;padding: 10px;}
		.reset {float: right; width: 35%; margin-top: 30px;}
		</style>
		<body>
		<form action="" method="POST">
		<h1>LCC Volunteer Scheduling System</h1>
		<p>Instructional video is here: <a href="instructions.html">Instructions</a></p>
		<h2>You need to log in to use this site</h2>
		<p>If you have never logged in before, your password is blank, but your username will be your first and last name lowercase with underscores instead of any spaces (i.e. jeff_mikels).</p>
		</ul>
		<hr />
			<?php if (isset($msg)) : ?><div class="alert"><?php print $msg; ?></div><?php endif; ?>
			<div id="reset" class="reset">
			<input type="checkbox" id="forgot" name="forgot" onclick="document.getElementById('password').style.display='none';document.getElementById('password-label').style.display='none';document.getElementById('username-label').innerHTML='email';document.getElementById('username').value='';document.getElementById('username').focus();" />
			<strong>reset my password</strong>
			<ul>
				<li><small>If you can't log in, check this box and enter your email address above. We'll send you a new password by email.</small>
				<li><small>If you still can't login, contact Pastor Jeff by phone at 404-0807.</small>
			</ul>
			</div>
			<label for="username" id="username-label">username:</label><input type="text" name="username" id="username" />
			<label for="password" id="password-label">password:</label><input type="password" name="password" id="password" />
			<br /><input type="submit" name="login_submit" value="submit" />
		</form>
		</body>
		</html>
		<?php
		exit();
	}
}
function supports_fixed_position()
{
	$agent_data = Array();
	$vuser_agent_string = $_SERVER['HTTP_USER_AGENT'];
	if (preg_match ('/AppleWebKit\/([^ ]*) /', $vuser_agent_string, $agent_data))
	{
		if ($agent_data[1] < 534) return false;
	}
	return true;
}



/*************************************
*
* CLASSES
*
*************************************/
class CiviCRM
{

	function get_group ($id = '')
	{
		$command = 'civicrm/group/get';
		$group = civicrm_api('Group','Get',array('id' => $id, 'version' =>3, 'rowCount' => 1));
		return $group['values'][0];
	}

	function get_volunteers()
	{
		$volunteers = civicrm_api('Contact', 'Get', array('filter.group_id' => 15, 'sort' => 'contact_id', 'contact_is_deleted'=>0, 'version' =>3, 'rowCount' => 1000));
		return $volunteers['values'];
	}

	function get_group_members($group_id)
	{
		$members = civicrm_api('Contact','Get',array('filter.group_id' => $group_id, 'contact_is_deleted'=>0, 'version' =>3, 'rowCount' => 1000));
		return $members['values'];
	}
}

class VDataBase
{
	private $dbhost='';
	private $dbuser='';
	private $dbpass='';
	private $dbname='';
	private $db = '';
    private $connected = false;

	function __construct($dbname, $dbhost, $dbuser, $dbpass)
	{
		$this->dbname = $dbname;
		$this->dbhost = $dbhost;
		$this->dbuser = $dbuser;
		$this->dbpass = $dbpass;
		$this->connect();
	}

	function connect()
	{
//        if ($this->connected) return;
		$db = mysql_connect($this->dbhost, $this->dbuser, $this->dbpass);
		mysql_select_db($this->dbname, $db);
		$this->db = $db;
        $this->connected = true;
	}

	function save($table,$object)
	{
		//_debug($object);
		$pairs = Array();

		foreach ($object as $key=>$value)
		{
			$pairs[] = $key . "='" . mysql_real_escape_string($value) . "'";
		}
		$pairs = implode(',',$pairs);
		if (isset($object['id']))
		{
			$q = "UPDATE `{$table}` SET {$pairs} WHERE id={$object['id']}";
			$this->query($q);
			return $object['id'];
		}
		else
		{
			$q = "INSERT INTO `{$table}` SET {$pairs}";
			$result = $this->query($q);
			return $result['id'];
		}
	}

	function query($q)
	{
		global $query_count;
		global $query_time;
		global $query_history;

		$query_count += 1;
		$ts = microtime(true);

		$this->connect();

		//_debug($q);
		$query_time_only = microtime(true);
		$res = mysql_query($q, $this->db);
		$query_time_only = microtime(true) - $query_time_only;
		if ( $res === True)
		{
			return array('rows' => mysql_affected_rows(), 'id' => mysql_insert_id());
		}

		elseif (!$res)
		{
			$message  = 'Invalid query: ' . mysql_error() . "\n";
			$message .= 'Whole query: ' . $q;
			die($message);
		}
		else
		{
			$retval = Array();
			while ($row = mysql_fetch_assoc($res)) {
				if ( isset ($row['id'] ) ) $retval[$row['id']] = $row;
				else $retval[] = $row;
			}
		}
		mysql_free_result($res);

		$query_duration = microtime(true) - $ts;
		$query_history[] = Array('query' => $q, 'duration' => $query_duration, 'query_only_duration' => $query_time_only, 'php_time' => $query_duration - $query_time_only);
		$query_time += $query_duration;

		return $retval;
	}

    function get_person_by_id($id)
    {
        $id = mysql_real_escape_string($id);
        $q = sprintf('SELECT * FROM people WHERE id=%d LIMIT 1', $id);
        $result = $this->query($q);
		if ($result) return reset($result);
		else return FALSE;
	}

	function get_person_by_email($email)
	{
		$email = mysql_real_escape_string($email);
		$q = "SELECT * FROM people WHERE email='{$email}' LIMIT 1";
		//_debug($q);
		$result = $this->query($q);
		//_debug($result);
		if ($result) return reset($result);
		else return FALSE;
	}

	function get_person_by_credentials($vusername, $password)
	{
		$vusername = mysql_real_escape_string($vusername);
		if ($password != "")
		{
			$password = mysql_real_escape_string($password);
			$hash = md5($password);
		}
		else $hash = '';
		$q = "SELECT * FROM people WHERE username='{$vusername}' AND password='{$hash}' LIMIT 1";
		//$q = "SELECT * FROM people WHERE username='{$vusername}' AND password='{$password}' LIMIT 1";
		//_debug($q);
		$result = $this->query($q);
		//_debug($result);
		if ($result) return reset($result);
		else return FALSE;
	}

	function get_volunteers_and_groups()
	{
		$query="SELECT people.id AS pid, people.name AS pn, groups.name AS gn, groups.id as gid FROM people, groups, person2group WHERE person2group.person_id = people.id AND person2group.group_id = groups.id GROUP BY pn, gn ORDER BY people.lastname ASC";
		return $this->query($query);
	}

	function get_volunteers()
	{
		$q = "SELECT *, people.id AS pid, people.name AS pn FROM people ORDER BY people.lastname ASC";
		return $this->query($q);
	}

	// get_volunteer returns a volunteer
	function get_volunteer($id)
	{
		$q="SELECT *, people.id AS pid, people.name AS pn FROM people WHERE people.id = '$id' LIMIT 1";
		$result = $this->query($q);
		return reset($result);
	}

	function get_upcoming_jobs_for_person($pid, $upcoming_only = TRUE)
	{
		if ($upcoming_only)
			$q = "SELECT *, jobs.id AS jid, jobs.name AS jn FROM jobs, person2job WHERE jobs.datetime_start > CURDATE() AND jobs.id = person2job.job_id AND person2job.person_id = '$pid' ORDER BY jobs.datetime_start ASC";
		else
			$q = "SELECT *, jobs.id AS jid, jobs.name AS jn FROM jobs, person2job WHERE jobs.id = person2job.job_id AND person2job.person_id = '$pid' ORDER BY jobs.datetime_start ASC";
		return $this->query($q);
	}

	function get_groups_for_volunteer($pid)
	{
		$q = "SELECT *, groups.id as gid, groups.name as gn FROM groups, person2group WHERE groups.id = person2group.group_id AND person2group.person_id = '$pid'";
		$results = $this->query($q);
		$retval = Array();
		foreach ($results as $result)
		{
			$retval[$result['gid']] = $result;
		}
		return $retval;
	}


	function get_exclusion($eid)
	{
		$q = "SELECT *, datetime_start AS eds, datetime_end AS ede FROM exclusions WHERE id={$eid} LIMIT 1";
		$result = $this->query($q);
		if ($result) return reset($result);
		else return NULL;
	}

	function get_exclusions_for_volunteer($pid)
	{
		$q = "SELECT *, datetime_start AS eds, datetime_end AS ede FROM exclusions WHERE person_id={$pid} AND datetime_end > CURDATE()";
		return $this->query($q);
	}

	function get_jobs_for_group($gid)
	{
		$q = "SELECT jobs.id as jid, jobs.name as jn FROM jobs WHERE jobs.default_group='$gid'";
		return $this->query($q);
	}

	function get_volunteers_for_group($gid)
	{
		$q = "SELECT *, people.id as pid, people.name as pn FROM people, person2group WHERE people.id = person2group.person_id AND person2group.group_id = '$gid' ORDER BY people.lastname ASC";
		return $this->query($q);
	}

	function get_volunteers_for_job($jid)
	{
		$q = "SELECT *, people.id as pid, people.name as pn FROM people, person2job WHERE people.id = person2job.person_id AND person2job.job_id = '$jid' ORDER BY people.lastname ASC";
		return $this->query($q);
	}

	function get_volunteer_busy_times($pid)
	{
		// get job times this volunteer is assigned to
		$q = "SELECT jobs.datetime_start AS jds, jobs.datetime_end AS jde, jobs.name AS name FROM jobs, person2job WHERE jobs.datetime_start > CURDATE() AND jobs.id = person2job.job_id AND person2job.person_id = {$pid}";
		$jobs = $this->query($q);

		// get exclusion times this volunteer has set
		$q = "SELECT exclusions.datetime_start AS eds, exclusions.datetime_end AS ede, exclusions.reason AS reason FROM exclusions WHERE {$pid} = exclusions.person_id AND exclusions.datetime_end > CURDATE()";
		$exclusions = $this->query($q);

		return Array('jobs' => $jobs, 'exclusions' => $exclusions);
	}

	function get_available_volunteers($jid)
	{
		$retval = Array();
		$retval['available'] = Array();
		$retval['busy'] = Array();

		$job = $this->get_job($jid);
		$datetime_start = $job['datetime_start'];
		$datetime_end = $job['datetime_end'];
		$default_group = $this->get_group($job['default_group']);
		$gid = $default_group['gid'];

		// now, get all volunteers who are on that team and who are not on this job already
		$q = "SELECT people.id as pid, people.name as pn FROM people, person2group WHERE people.id = person2group.person_id AND person2group.group_id = {$gid} AND people.id NOT IN (SELECT person_id FROM person2job WHERE person2job.job_id = {$jid}) ORDER BY people.lastname ASC";
		$volunteers = $this->query($q);
		foreach ($volunteers as $volunteer)
		{
			//_debug($volunteer, 'checking availability for ');
			$is_busy = FALSE;
			$busy_data = $this->get_volunteer_busy_times($volunteer['pid']);
			//_debug($busy_data);
			foreach ($busy_data['jobs'] as $otherjob)
			{
				if ( time_overlaps($datetime_start, $datetime_end, $otherjob['jds'], $otherjob['jde']) )
				{
					$is_busy = TRUE;
					$retval['busy'][] = array('volunteer' => $volunteer, 'reason'=> 'ASSIGNED TO: ' . $otherjob['name'],'category' => 'job', 'job' => $otherjob);
				}
			}
			foreach ($busy_data['exclusions'] as $exclusion)
			{
				if ( time_overlaps($exclusion['eds'], $exclusion['ede'], $datetime_start, $datetime_end ))
				{
					$is_busy = TRUE;
					$retval['busy'][] = array('volunteer' => $volunteer, 'reason'=>$exclusion['reason'], 'category' => 'exclusion', 'exclusion' => $exclusion);
				}
			}
			if ( ! $is_busy ) $retval['available'][] = $volunteer;
		}
		return $retval;
	}

	function get_upcoming_jobs_and_volunteers($group_id='')
	{
		// this query didn't really work too well
// 		$q = "SELECT jobs.*, people.name as pn, people.id as pid FROM jobs, people, person2job WHERE jobs.datetime_start > CURDATE() AND people.id=person2job.person_id AND jobs.id = person2job.job_id GROUP BY jobs.id, pn ORDER BY jobs.datetime_start ASC";
// 		return $this->query($q);

		$events = Array();
		$jobs = $this->get_upcoming_jobs();
		foreach ($jobs as $job)
		{
			if ($group_id !== '' and $group_id != $job['default_group']) continue;
			$job['people'] = $this->get_volunteers_for_job($job['jid']);
			$job['pn'] = Array();
			foreach ($job['people'] as $person)
			{
				$job['pn'][] = $person['name'];
			}
			$job['pn'] = implode(', ', $job['pn']);
			$events[] = $job;
		}
		return $events;
	}

	function get_upcoming_jobs()
	{
		$q = "SELECT *, jobs.name as jn, jobs.id as jid FROM jobs WHERE jobs.datetime_start > CURDATE() ORDER BY jobs.datetime_start ASC";
		return $this->query($q);
	}

	function get_upcoming_jobs_for_group($gid = '')
	{
		if ($gid == '')
			$q = "SELECT *, jobs.name as jn, jobs.id as jid FROM jobs WHERE jobs.datetime_start > CURDATE() ORDER BY jobs.datetime_start ASC";
		else
			$q = "SELECT *, jobs.name as jn, jobs.id as jid FROM jobs WHERE jobs.default_group = $gid AND jobs.datetime_start > CURDATE() ORDER BY jobs.datetime_start ASC";
		return $this->query($q);
	}

	function get_job_templates()
	{
		$q = "SELECT *, jobs.id as jid, jobs.name as jn FROM jobs WHERE jobs.is_template = TRUE";
		return $this->query($q);
	}

	function get_job($jid)
	{
		$q = "SELECT *, jobs.id as jid, jobs.name as jn FROM jobs WHERE jobs.id = '$jid' LIMIT 1";
		$result = $this->query($q);
		return reset($result);
	}

	function get_groups()
	{
		$q = "SELECT *, groups.id as gid, groups.name as gn FROM groups";
		return $this->query($q);
	}

	function get_group($gid)
	{
		$q = "SELECT *, groups.name as gn, groups.id as gid FROM groups WHERE groups.id = '$gid' LIMIT 1";
		$result = $this->query($q);
		return reset($result);
	}
}






if ($action == 'sync')
{
	include "head.php";
	?>
	<body style="margin-top:150px;" onload="document.getElementById('progress').style.display = 'none';document.getElementById('done').style.display = 'block';">
	<div style="width: 100%;position:absolute;top:0;left:0;">
	<div id="progress" style="background: white; border: 3px solid red; padding: 20px; position:relative;" class="alert">
		<p>performing sync...</p>
		<center>
		<img src="progress.gif" />
		</center>
	</div>

	<div id="done" style="display:none;background: white; border: 3px solid red; padding: 20px; position:relative;" class="alert">
		SYNC COMPLETED. If there were no errors, <a href="?">click here</a> to return to the volunteer home page.
	</div>
	</div>

	<?php update_groups(); ?>

	</body>
	</html>
	<?php
	//Header ('Location: ?');
	exit();
}
//_debug($_SESSION);
?>
