<?php
include 'include.php';
//$user=$_SESSION['lcc_volunteers_user'];

// IF ICAL IS REQUESTED, JUMP TO THAT
if($action == 'ical')
{
	global $events;
	global $calendar_name;
	global $db;
	if (!$object)
	{
		// show all upcoming jobs.
		$assignments = $db->get_upcoming_jobs_and_volunteers();
		$calendar_name = "[LCC] All Volunteers Job Assignments";
		$events = Array();
		foreach ($assignments as $assignment)
		{
			$id = $assignment['id'];
			$person = $assignment['pn'];
			if (isset($events[$id]))
			{
				$events[$id]['pn'] .= ', ' . $person;
			}
			else
			{
				$events[$id] = $assignment;
			}
		}
	}
	if ($object == 'person')
	{
		// show all jobs for person
		// show all exclusions for person
		$person = $db->get_volunteer($id);
		$calendar_name = "[LCC] {$person['pn']}'s Job Assignments";
		$assignments = $db->get_upcoming_jobs_for_person($id);
		$exclusions = $db->get_exclusions_for_volunteer($id);
		$events = $assignments;
		foreach ($exclusions as $exclusion)
		{
			$events[] = array(
				'id' => 'e'.$exclusion['id'],
				'name' => 'TIME OFF',
				'description' => $exclusion['reason'],
				'datetime_start' => $exclusion['eds'],
				'datetime_end' => $exclusion['ede']
			);
		}
	}
	if ($object == 'group')
	{
		// show all jobs for group
		// put person assignments in the description
		$gid = $id;
		$group = $db->get_group($gid);
		$calendar_name = "[LCC] {$group['name']}'s Job Assignments";
		$assignments = $db->get_upcoming_jobs_and_volunteers();
		//print_r($assignments);
		$events = Array();
		foreach ($assignments as $assignment)
		{
			if ($assignment['default_group'] != $gid) continue;
			$aid = $assignment['id'];
			$person = $assignment['pn'];
			if (isset($events[$aid]))
			{
				$events[$aid]['pn'] .= ', ' . $person;
			}
			else
			{
				$events[$aid] = $assignment;
			}
		}
	}
	include "ical.php";
	exit();
}
?>
<?php include 'head.php'; ?>
<body>

<?php
global $crm;
$volunteer_groups = $crm->get_group_and_children(15);
debug($volunteer_groups);
die();
?>

<div style="float: right;"><?php make_link(array('action'=>'sync'), $text="[[ ReSync with LCC Members ]]"); ?></div>
<h1><a href="/volunteers">LCC Volunteers</a></h1>

<!-- MENU -->
<div class="menu">
	<ul>
		<li class="right"><a href="?action=edit&object=person&id=<?php print $user['id']; ?>"><small>(change password)</small></li>
		<li class="right"><a href="?action=show&object=person&id=<?php print $user['id']; ?>"><?php print $user['name']; ?></li>

		<li><a href="?">Home</a></li>
		<li><a href="?object=job&action=new">Add Job</a></li>
		<li><a href="?object=exclusion&action=new" title="Request Time Off">Request Time Off (Add Exclusion)</a></li>
		<?php if ($action == 'show'): ?>
		<li><?php show_edit_link(); ?></li>
		<?php endif; ?>
	</ul>
</div>

<?php if ($msg) print "<div class='alert' id='alert_box'>$msg</div>"; ?>

<?php if (!$object AND !$action) : ?>
<!-- DEFAULT VIEW -->

<div class="col1">
<?php show_ical_link(); ?>
<h2>Upcoming Jobs</h2>
<?php show_upcoming_jobs(); ?>
</div>

<div class="col2">
<h2>Volunteers</h2>
<?php show_volunteers(); ?>
</div>

<?php elseif ($object == 'job') : ?>
	<!-- JOB VIEWS -->

	<?php if ($id and $action=='show') : ?>

		<!-- SHOW JOB -->
		<div class="col1">
		<?php show_ical_link(); ?>
		<h2>Job Details</h2>
		<?php show_job($id); ?>
		</div>

		<div class="col2">
		<h2>Available</h2>
		<?php show_available_volunteers($id); ?>
		</div>

	<?php elseif ($id and $action=='edit') : ?>

		<!-- EDIT JOB -->
		<div class="col1">
		<h2>Edit Job</h2>
		<?php show_job_form($id); ?>
		</div>

		<div class="col2">
		<h2>Available</h2>
		<?php show_available_volunteers($id); ?>
		</div>

	<?php elseif ($action == 'new') : ?>

		<!-- EDIT JOB -->
		<div class="col1">
		<h2>Edit Job</h2>
		<?php show_job_form(0); ?>
		</div>

		<div class="col2">
		<h2>Job Templates</h2>
		<?php show_job_templates(); ?>
		</div>

	<?php else : show_error(); ?>
	<?php endif; ?>
	
<?php elseif ($object == 'group') : ?>
	<?php if ($id and $action = 'show') : ?>
	<?php show_ical_link(); ?>
	<?php show_group($id); ?>
	<?php endif; ?>
	
<?php elseif ($object == 'person') : ?>

	<!-- PERSON VIEWS -->
	<?php if ($id and $action=='show') : ?>

		<!-- SHOW PERSON -->
		<div class="col1">
		<?php show_ical_link(); ?>
		<h2>Person Details</h2>
		<?php show_volunteer($id, $details=TRUE); ?>
		
		<h2>Upcoming Jobs for this Person</h2>
		<?php show_jobs_for_volunteer($id); ?>
		</div>

		<div class="col2">
		<h2>Exclusions (Time Off)</h2>
		<?php show_exclusions_for_volunteer($id); ?>
		</div>

	<?php elseif($id and $action == 'edit') : ?>

		<!-- SHOW PERSON FORM -->
		<div class="col1">
		<h2>Person Details</h2>
		<?php show_volunteer_form($id, $details=TRUE); ?>
		</div>

		<div class="col2">
		<h2>Exclusions</h2>
		<?php show_exclusions_for_volunteer($id); ?>
		</div>

	<?php else : show_error(); ?>
	<?php endif; ?>

<?php elseif ($object == 'exclusion') : ?>
	<!-- EXCLUSION VIEWS -->
	<?php if ($id and $action=='show') : ?>

		<!-- SHOW EXCLUSION -->
		<div class="col1">
		<h2>Exclusion Details</h2>
		<?php show_exclusion($id); ?>
		</div>

		<div class="col2">
		<h2>Volunteers</h2>
		<?php show_volunteers(); ?>
		</div>

	<?php elseif ($id and $action=='edit') : ?>

		<!-- EDIT EXCLUSION -->
		<div class="col1">
		<h2>Exclusion Details</h2>
		<?php show_exclusion_form($id); ?>
		</div>

		<div class="col2">
		<h2>Volunteers</h2>
		<?php show_volunteers(); ?>
		</div>

	<?php elseif ($action == 'new') : ?>

		<!-- EDIT EXCLUSION -->
		<div class="col1">
		<h2>Add Exclusion</h2>
		<?php show_exclusion_form(0); ?>
		</div>

		<div class="col2">
		<h2>Volunteers</h2>
		<?php show_volunteers(); ?>
		</div>

	<?php else : show_error(); ?>
	<?php endif; ?>
<?php endif; ?>

<div class="footer">
<?php
	//debug($_POST);
	//debug($_SESSION);
?>
</div>
</body>
<script type="text/javascript">
$("#alert_box").delay(2000).slideUp(1000);
</script>
</html>