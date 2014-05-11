<?php
include 'include.php';
global $query_time;
global $query_count;
global $query_history;

// HOME PAGE:
// list all jobs for the next six months - with links to edit
// list all active volunteers sorted by teams - with links to view people

// MENU:
// add job
// add exclusion

// BACKEND
// ability to update google calendar

// IF ICAL IS REQUESTED, JUMP TO THAT
if($action == 'ical')
{
	global $events;
	global $calendar_name;
	global $_volunteer_db;
	if (!$object)
	{
		// show all upcoming jobs.
		$calendar_name = "[LCC] All Volunteers Job Assignments";
		$events = $_volunteer_db->get_upcoming_jobs_and_volunteers();
// 		print_r($assignments);
// 		exit();
// 		$events = Array();
// 		foreach ($assignments as $assignment)
// 		{
// 			$id = $assignment['id'];
// 			$person = $assignment['pn'];
// 			if (isset($events[$id]))
// 			{
// 				$events[$id]['pn'] .= ', ' . $person;
// 			}
// 			else
// 			{
// 				$events[$id] = $assignment;
// 			}
// 		}
	}
	if ($object == 'person')
	{
		// show all jobs for person
		// show all exclusions for person
		$person = $_volunteer_db->get_volunteer($id);
		$calendar_name = "[LCC] {$person['pn']}'s Job Assignments";
		$assignments = $_volunteer_db->get_upcoming_jobs_for_person($id);
		$exclusions = $_volunteer_db->get_exclusions_for_volunteer($id);
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
		$group = $_volunteer_db->get_group($gid);
		$calendar_name = "[LCC] {$group['name']}'s Job Assignments";
		$events = $_volunteer_db->get_upcoming_jobs_and_volunteers($gid);
// 		//print_r($assignments);
// 		$events = Array();
// 		foreach ($assignments as $assignment)
// 		{
// 			if ($assignment['default_group'] != $gid) continue;
// 			$aid = $assignment['id'];
// 			$person = $assignment['pn'];
// 			if (isset($events[$aid]))
// 			{
// 				$events[$aid]['pn'] .= ', ' . $person;
// 			}
// 			else
// 			{
// 				$events[$aid] = $assignment;
// 			}
// 		}
	}
	include "ical.php";
	exit();
}
?>
<?php include 'head.php'; ?>
<body>
<div class="logo">&nbsp;</div>
<div class="header">
	<div style="float: right;"><?php make_link(array('action'=>'sync'), $text="[[ ReSync with LCC Members ]]"); ?></div>
	<h1><a href="<?php echo $site_url; ?>">LCC Volunteers</a></h1>
</div>

<div class="footer">
	<img src="http://lafayettecc.org/news/wp-content/themes/lcc.2/images/logo-ball-on-black.png" />
</div>

<!-- MENU -->
<div class="menu">
	<ul>
		<li class="right"><a href="?reset=1">Log Out</li>
		<li class="right"><a href="?action=edit&object=person&id=<?php print $vuser['id']; ?>"><small>(change password)</small></li>
		<li class="right"><a href="?action=show&object=person&id=<?php print $vuser['id']; ?>"><?php print $vuser['name']; ?></li>


		<li><a href="?">Home</a></li>
		<li><a href="?object=job&action=new">Add Job</a></li>
		<li><a href="?object=exclusion&action=new" title="Request Time Off">Request Time Off (Add Exclusion)</a></li>
		<?php if ($action == 'show'): ?>
		<li><?php show_edit_link(); ?></li>
		<?php endif; ?>
	</ul>
</div>
<div class="clear">&nbsp;</div>

<?php if ($msg) print "<div class='alert' id='alert_box'>$msg</div>"; ?>

<?php if (!$object AND !$action) : ?>
<!-- DEFAULT VIEW -->
<?php show_ical_link(); ?>
<div class="col1">
<h2>All Groups & Jobs</h2>
<?php $object = 'group'; ?>
<?php $action = 'show'; ?>

<?php $query_count = 0; $query_time = 0; $query_history = Array(); $ts=microtime(true);?>
<?php $group_job_data = get_all_upcoming_by_group(); ?>
<?php foreach ($group_job_data as $group_id=>$group_data): ?>
    <?php show_group_chart($group_data); ?>
<?php endforeach; ?>
<?php _debug("Ran $query_count queries in $query_time seconds. Total processing time: " . (microtime(true) - $ts)); ?>
<?php _debug($query_history); ?>
</div>
    
<div class="col2">
<h2>Teams</h2>
<div class="jobgroup">
<?php foreach ($volunteer_groups as $name=>$gid): ?>
<a style="line-height: 2em; padding: 5px; white-space: nowrap;" href="<?php echo $site_url; ?>/?action=show&object=group&id=<?php print $gid; ?>"><?php print $name; ?></a>
<?php endforeach; ?>
</div>
<h2>Volunteers</h2>
<?php show_volunteers(); ?>
</div>

<?php elseif ($object == 'job') : ?>
	<!-- JOB VIEWS -->

	<?php if ($id and $action=='show') : ?>

		<!-- SHOW JOB -->
		<?php show_ical_link(); ?>
		<div class="col1">
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
		<h2>Use a Job Template</h2>
			<?php show_job_templates(); ?>
		</div>

	<?php elseif ($action == 'new') : ?>

		<!-- EDIT JOB -->
		<div class="col1">
		<h2>Edit Job</h2>
		<?php show_job_form(0); ?>
		</div>

		<div class="col2">
		<h2>Use a Job Template</h2>
		<?php show_job_templates(); ?>
		</div>

	<?php else : show_error(); ?>
	<?php endif; ?>

<?php elseif ($object == 'group') : ?>
	<?php if ($id and $action = 'show') : ?>
	<div class="fullwidth">
	<?php show_ical_link(); ?>
	<?php show_group($id); ?>
	</div>
	<?php endif; ?>

<?php elseif ($object == 'person') : ?>

	<!-- PERSON VIEWS -->
	<?php if ($id and $action=='show') : ?>

		<!-- SHOW PERSON -->
		<?php show_ical_link(); ?>
		<div class="col1">
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

<div class="debug">
<?php
	//debug($_POST);
	//debug($_SESSION);
?>
</div>
<div class="bottom_spacer">&nbsp;</div>

</body>
<script type="text/javascript">
$("#alert_box").delay(2000).slideUp(1000);
</script>
</html>
