<?php
global $events;
global $calendar_name;
global $object;
global $id;
Header ("Content-Type: text/plain");
//Header('Content-Disposition: attachment; filename="lcc-volunteers-calendar.ics"');
$output = "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Lafayette Community Church//NONSGML LCC Volunteers//EN
X-WR-TIMEZONE:America/New_York
X-WR-CALNAME;VALUE=TEXT:" . $calendar_name . "\n";

//print_r($events);
//exit();
foreach ($events as $event)
{
$dtstart = tstamp($event['datetime_start']);
$dtend = tstamp($event['datetime_end']);
$name = $event['name'];
$participants = '';
if (isset($event['pn'])) $participants = ' (with ' . $event['pn'] . ')';
$uid = $event['id'];
$description = $event['description'];
$description = str_replace("\r\n", "\n", $description);
$description = str_replace("\n", "\\N", $description);
if (isset($event['people'])) $description = $description . "\N\NPARTICIPANTS:";
foreach ($event['people'] as $person)
{
	$description .= "\N" . $person['name'] . " -- " . $person['note'];
}
$description = str_replace(",", "\\,", $description);

// prepare links for description
$homelink = "http://lafayettecc.org/volunteers/";
$links = Array();
$links[] = Array('name' => 'VOLUNTEER SITE', 'link' => $homelink);

// this is the link to the page from which the ics file was taken
if ($object and $id)
{
	$link = $homelink . "?action=show&object=" . urlencode($object) . "&id=" . urlencode($id);
	$links[] = Array('name' => "THIS CALENDAR LINK", 'link' => $link);
}

// this is the link to the specific job
$joblink = $homelink . "?action=show&object=job&id=" . urlencode($event['jid']);
$links[] = array('name' => "JOB LINK", 'link' => $joblink);

// this is the link to the group page for this job
$grouplink = $homelink . "?action=show&object=group&id=" . urlencode($event['default_group']);
$links[] = array('name' => "MINISTRY TEAM JOB CHART", 'link' => $grouplink);

$description .= "\N\NLINKS:";
foreach ($links as $link)
{
	$description .= "\N" . $link['name'] . ": " . $link['link'];
}

$output .= "BEGIN:VEVENT
UID:$uid
SEQUENCE:2
DTSTAMP:{$dtstart}Z
ORGANIZER;CN=Pastor Jeff:MAILTO:pastor@lafayettecc.org
DTSTART;TZID=America/New_York:{$dtstart}
DTEND;TZID=America/New_York:{$dtend}
SUMMARY:{$name}{$participants}
DESCRIPTION:$description
END:VEVENT
";
}
$output .= "END:VCALENDAR";
$output = str_replace("\r\n", "\n", $output);
$output = str_replace("\n\n", "\n", $output);
$output = str_replace("\n", "\r\n", $output);
print $output;
?>