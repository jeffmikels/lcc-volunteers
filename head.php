<!DOCTYPE html>
<html>
<head>

<title>LCC Volunteer Scheduling</title>
<link rel="stylesheet" href="css/anytimec.css" type="text/css" media="all" />
<link href='http://fonts.googleapis.com/css?family=Noto+Sans:400,700,400italic,700italic' rel='stylesheet' type='text/css'>

<!--<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js" type="text/javascript"></script>-->
<script src="http://code.jquery.com/jquery-1.10.1.min.js"></script>
<script src="http://code.jquery.com/jquery-migrate-1.2.1.min.js"></script>

<script src="js/anytimec.js" type="text/javascript"></script>
<style type="text/css">
body { background: url("gradient.png") repeat-x; font-family: "Noto Sans", Futura, "Segoe UI", Calibri, sans-serif; width:960px;margin:auto;font-size: 15px;padding-bottom:100px;}
.logo {position:absolute;top:0;left:0;z-index:-99;padding:0; margin:0;width:100%;height:941px;background:url('ball.png') no-repeat -400px 0;opacity:.1;}
.footer {position: fixed; bottom:0; left: 0; width:100%; padding:5px 0; margin: 0;background:black;}
.footer img {padding: 2px 10px;}
.bottom_spacer {height: 100px;}
.footer {display:none;}

.fullwidth {padding: 0;}

.col1 {width: 640px;padding:0 20px 20px 20px; overflow:auto;float:left;}
.col2 {margin-left: 20px;width: 240px;padding:0 0 20px 0;overflow:none;float:left;}
.debug {background: #aaa;overflow:none;clear:both;}
.menu {width:960px; background: #eee; display: block; color: black; font-weight: bold;overflow:none;float:left;margin-bottom:5px;}
.menu ul {padding: 0; margin: 0; list-style: none;overflow:none;}
.menu ul li {padding: 5px 10px; margin: 0; list-style: none; display: block; float:left;line-height: 1.5em;}

.jobtitle {margin-bottom: 3px;margin-top:3px;}
.jobgroup {font-size: .7em; font-weight: bold;margin-top: 25px;color: #ada;}
.jobdescription {margin-left: 10px;font-size: .9em;}

.block {padding: 3px 10px; background: #efe; border: #ada;margin-bottom: 20px;}
.busy {margin:10px 0; font-size: 1em;}
.busy .reason {line-height: 1em; font-size: .8em;}
.busy li {margin: 10px 0 0;}

.off {margin:10px 0; font-size: 1em;}
.off .reason {line-height: 1em; font-size: .8em;}
.off li {margin: 10px 0 0;}


.datetime {padding: 0 10px; font-size: .6em;}

.assignment_link {display: block; float:left;padding: 3px 5px; border:2px solid black;margin:0 10px 0 0;line-height: 40px;}
.unassignment_link {padding-left: 5px;padding-right:5px;}
.assignment_link:hover,
.unassignment_link:hover {background: #ada;}

.alert { clear: both; padding: 40px; line-height: 30px; color:red; border:3px solid red; margin: 20px 0;}

h1, h2, h3, h4 {color: #101090;}
h2 { border-bottom: 1px solid #101090;margin-top:20px;}

a {text-decoration: none; color:#5050e0;font-weight: bold;}
a:hover {color: #101090;}
h1 a {color: #101090;}

pre {font-size: .6em;}

.details {margin: 0 0 0 10px; font-size: .8em;}

li {list-style: none; margin: 0;}

ul {margin: 0;padding: 0;}
ul li {list-style: none;margin: 10px 0 10px;}


li.volunteer {margin-bottom: 20px; border-bottom: 1px solid #dee;}

ul.tags {display: block; font-size: 10px; list-style: none; margin: 0; padding: 0;}
ul.tags li.tag {padding: 1px 3px; margin:0; color: #494; list-style:none; display: inline;white-space: nowrap;}
ul.tags li.tag:hover {background: #494; color: white;}
ul.tags a {color: inherit; background: inherit;}

.jobgroup a {color: blue; padding: 1px 5px; margin-left: -5px;}
.jobgroup a:hover {color: white; background:blue;}

ul.available li {margin: 0 0 20px 40px; min-height: 50px; }
ul.busy li {margin: 0 0 0 0; }
.reason {font-weight: bold; color: red;margin-bottom: 10px;}

textarea {width: 500px; }
label {display: block;}

.left {float:left;margin-left:0;}
.right {float:right !important; margin-right:0;}
.clear {clear: both;height:0}

.red {color:red !important;}

div.job_chart {width:936px; margin:10px auto; border: 10px solid #eee; border-radius: 20px; padding: 3px; overflow:auto;background:white;}
.col1 div.job_chart {width: 614px;}

table.job_chart {font-size: 10px; width: 100%; margin:auto;}
table.job_chart td {text-align: center;font-size: 10px; min-width:50px;}
table.job_chart td.available {background: #eee;}
table.job_chart td.assigned {background: #9f9;}
table.job_chart td.busy {background: #f88;}
table.job_chart td.off {background: pink;}
table.job_chart a.unassignment_link,
table.job_chart a.assignment_link
{
	width: 100%;
	margin: auto 0;
	padding: 10px 0;
	font-size: 10px;
	background: transparent;
	color: inherit;
	float: none;
	display: block;
	line-height: inherit;
	border:none;
	font-weight: normal;
}
table.job_chart a.assignment_link {font-size:.8em;}
table.smaller {font-size: 9px; letter-spacing: -.5px; width: 90%;border-width: 5px;}
table.smaller td, table.smaller a {font-size: 9px;}

/* CORRECTIONS FOR THE TABLE OVERLAY CODE */
div.job_chart{position:relative;overflow:hidden;}
div.job_chart_main{position:relative;top:0px;overflow:auto;}
div.job_chart_overlay{position:absolute;top:3px;}
div.job_chart_overlay{background:rgba(255,255,255,.8);color:red;width:150px;overflow:hidden;}
.first_column {display:inline-block;width:150px;line-height:30px;}
div.job_chart td{min-height:30px;}



div.ical_links {margin: 0px; padding: 10px; border: 2px solid #eef; border-radius: 5px;background:white;}
a.ical_link, a.webcal_link, a.gcal_link {line-height: 20px;}
a.ical_link:hover, a.webcal_link:hover, a.gcal_link:hover {background-color: #afa;}
a.ical_link {float: right; padding-left: 20px; padding-right: 20px; font-size: 10px;}
a.webcal_link {float: right; padding-left: 20px; padding-right: 20px; background: url(ical.png) no-repeat; font-size: 10px;}
a.gcal_link {float: right; padding-left: 20px; padding-right: 20px; background: url(gcal.png) no-repeat; font-size: 10px;}

input{font-size: 12pt;font-family: Verdana, sans;}
textarea{font-size: 16pt;font-family: Verdana;}
textarea, input[type=text] {font-size: 16pt; padding:5px; border:1px solid gray; border-radius: 5px;}




.button {
	display: inline-block;
	outline: none;
	cursor: pointer;
	text-align: center;
	text-decoration: none;
	font: 14px/100% Arial, Helvetica, sans-serif;
	padding: .5em 2em .55em;
	text-shadow: 0 1px 1px rgba(0,0,0,.3);
	-webkit-border-radius: .5em;
	-moz-border-radius: .5em;
	border-radius: .5em;
	-webkit-box-shadow: 0 1px 2px rgba(0,0,0,.2);
	-moz-box-shadow: 0 1px 2px rgba(0,0,0,.2);
	box-shadow: 0 1px 2px rgba(0,0,0,.2);
}
.button:hover {
	text-decoration: none;
}
.button:active {
	position: relative;
	top: 1px;
}


/* color styles
---------------------------------------------- */

/* black */
.black {
	color: #d7d7d7;
	border: solid 1px #333;
	background: #333;
	background: -webkit-gradient(linear, left top, left bottom, from(#666), to(#000));
	background: -moz-linear-gradient(top,  #666,  #000);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#666666', endColorstr='#000000');
}
.black:hover {
	background: #000;
	background: -webkit-gradient(linear, left top, left bottom, from(#444), to(#000));
	background: -moz-linear-gradient(top,  #444,  #000);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#444444', endColorstr='#000000');
}
.black:active {
	color: #666;
	background: -webkit-gradient(linear, left top, left bottom, from(#000), to(#444));
	background: -moz-linear-gradient(top,  #000,  #444);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#000000', endColorstr='#666666');
}

/* gray */
.gray {
	color: #e9e9e9;
	border: solid 1px #555;
	background: #6e6e6e;
	background: -webkit-gradient(linear, left top, left bottom, from(#888), to(#575757));
	background: -moz-linear-gradient(top,  #888,  #575757);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#888888', endColorstr='#575757');
}
.gray:hover {
	background: #616161;
	background: -webkit-gradient(linear, left top, left bottom, from(#757575), to(#4b4b4b));
	background: -moz-linear-gradient(top,  #757575,  #4b4b4b);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#757575', endColorstr='#4b4b4b');
}
.gray:active {
	color: #afafaf;
	background: -webkit-gradient(linear, left top, left bottom, from(#575757), to(#888));
	background: -moz-linear-gradient(top,  #575757,  #888);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#575757', endColorstr='#888888');
}

/* white */
.white {
	color: #606060;
	border: solid 1px #b7b7b7;
	background: #fff;
	background: -webkit-gradient(linear, left top, left bottom, from(#fff), to(#ededed));
	background: -moz-linear-gradient(top,  #fff,  #ededed);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#ffffff', endColorstr='#ededed');
}
.white:hover {
	background: #ededed;
	background: -webkit-gradient(linear, left top, left bottom, from(#fff), to(#dcdcdc));
	background: -moz-linear-gradient(top,  #fff,  #dcdcdc);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#ffffff', endColorstr='#dcdcdc');
}
.white:active {
	color: #999;
	background: -webkit-gradient(linear, left top, left bottom, from(#ededed), to(#fff));
	background: -moz-linear-gradient(top,  #ededed,  #fff);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#ededed', endColorstr='#ffffff');
}

/* orange */
.orange {
	color: #fef4e9;
	border: solid 1px #da7c0c;
	background: #f78d1d;
	background: -webkit-gradient(linear, left top, left bottom, from(#faa51a), to(#f47a20));
	background: -moz-linear-gradient(top,  #faa51a,  #f47a20);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#faa51a', endColorstr='#f47a20');
}
.orange:hover {
	background: #f47c20;
	background: -webkit-gradient(linear, left top, left bottom, from(#f88e11), to(#f06015));
	background: -moz-linear-gradient(top,  #f88e11,  #f06015);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#f88e11', endColorstr='#f06015');
}
.orange:active {
	color: #fcd3a5;
	background: -webkit-gradient(linear, left top, left bottom, from(#f47a20), to(#faa51a));
	background: -moz-linear-gradient(top,  #f47a20,  #faa51a);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#f47a20', endColorstr='#faa51a');
}

/* red */
.redbutton {
	color: #faddde;
	border: solid 1px #980c10;
	background: #d81b21;
	background: -webkit-gradient(linear, left top, left bottom, from(#ed1c24), to(#aa1317));
	background: -moz-linear-gradient(top,  #ed1c24,  #aa1317);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#ed1c24', endColorstr='#aa1317');
}
.redbutton:hover {
	background: #b61318;
	background: -webkit-gradient(linear, left top, left bottom, from(#c9151b), to(#a11115));
	background: -moz-linear-gradient(top,  #c9151b,  #a11115);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#c9151b', endColorstr='#a11115');
}
.redbutton:active {
	color: #de898c;
	background: -webkit-gradient(linear, left top, left bottom, from(#aa1317), to(#ed1c24));
	background: -moz-linear-gradient(top,  #aa1317,  #ed1c24);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#aa1317', endColorstr='#ed1c24');
}

/* blue */
.blue {
	color: #d9eef7;
	border: solid 1px #0076a3;
	background: #0095cd;
	background: -webkit-gradient(linear, left top, left bottom, from(#00adee), to(#0078a5));
	background: -moz-linear-gradient(top,  #00adee,  #0078a5);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#00adee', endColorstr='#0078a5');
}
.blue:hover {
	background: #007ead;
	background: -webkit-gradient(linear, left top, left bottom, from(#0095cc), to(#00678e));
	background: -moz-linear-gradient(top,  #0095cc,  #00678e);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#0095cc', endColorstr='#00678e');
}
.blue:active {
	color: #80bed6;
	background: -webkit-gradient(linear, left top, left bottom, from(#0078a5), to(#00adee));
	background: -moz-linear-gradient(top,  #0078a5,  #00adee);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#0078a5', endColorstr='#00adee');
}

/* rosy */
.rosy {
	color: #fae7e9;
	border: solid 1px #b73948;
	background: #da5867;
	background: -webkit-gradient(linear, left top, left bottom, from(#f16c7c), to(#bf404f));
	background: -moz-linear-gradient(top,  #f16c7c,  #bf404f);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#f16c7c', endColorstr='#bf404f');
}
.rosy:hover {
	background: #ba4b58;
	background: -webkit-gradient(linear, left top, left bottom, from(#cf5d6a), to(#a53845));
	background: -moz-linear-gradient(top,  #cf5d6a,  #a53845);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#cf5d6a', endColorstr='#a53845');
}
.rosy:active {
	color: #dca4ab;
	background: -webkit-gradient(linear, left top, left bottom, from(#bf404f), to(#f16c7c));
	background: -moz-linear-gradient(top,  #bf404f,  #f16c7c);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#bf404f', endColorstr='#f16c7c');
}

/* green */
.green {
	color: #e8f0de;
	border: solid 1px #538312;
	background: #64991e;
	background: -webkit-gradient(linear, left top, left bottom, from(#7db72f), to(#4e7d0e));
	background: -moz-linear-gradient(top,  #7db72f,  #4e7d0e);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#7db72f', endColorstr='#4e7d0e');
}
.green:hover {
	background: #538018;
	background: -webkit-gradient(linear, left top, left bottom, from(#6b9d28), to(#436b0c));
	background: -moz-linear-gradient(top,  #6b9d28,  #436b0c);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#6b9d28', endColorstr='#436b0c');
}
.green:active {
	color: #a9c08c;
	background: -webkit-gradient(linear, left top, left bottom, from(#4e7d0e), to(#7db72f));
	background: -moz-linear-gradient(top,  #4e7d0e,  #7db72f);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#4e7d0e', endColorstr='#7db72f');
}

/* pink */
.pink {
	color: #feeef5;
	border: solid 1px #d2729e;
	background: #f895c2;
	background: -webkit-gradient(linear, left top, left bottom, from(#feb1d3), to(#f171ab));
	background: -moz-linear-gradient(top,  #feb1d3,  #f171ab);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#feb1d3', endColorstr='#f171ab');
}
.pink:hover {
	background: #d57ea5;
	background: -webkit-gradient(linear, left top, left bottom, from(#f4aacb), to(#e86ca4));
	background: -moz-linear-gradient(top,  #f4aacb,  #e86ca4);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#f4aacb', endColorstr='#e86ca4');
}
.pink:active {
	color: #f3c3d9;
	background: -webkit-gradient(linear, left top, left bottom, from(#f171ab), to(#feb1d3));
	background: -moz-linear-gradient(top,  #f171ab,  #feb1d3);
	filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#f171ab', endColorstr='#feb1d3');
}



/* overrides */
body {width:auto; margin:0 40px;min-width:480px;}
div.menu {width:100%;}
div.job_chart {width:100%; box-sizing:border-box;}
.col1 {box-sizing:border-box;width:70%;overflow:auto;}
.col2 {box-sizing:border-box;width:20%;}
.col1 div.job_chart {width:100%;box-sizing:border-box;}



</style>
</head>
