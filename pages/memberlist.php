<?php
//  AcmlmBoard XD - Member list page
//  Access: all
if (!defined('BLARG')) die();


$title = __("Member list");


if (!isset($http->get('group'))) 
	$http->get('group') = 'none';
else if ($http->get('group') !== 'staff' && $http->get('group') !== 'none')
	$_GET['group'] = (int)$http->get('group');


$allgroups = [];
$allgroups['none'] = __('(any)');
$g = Query("SELECT id,name,type FROM {usergroups} WHERE display>-1 ORDER BY type, rank");

$allgroups[__('Primary')] = null;
$s = false;
while ($group = Fetch($g)) {
	if (!$s && $group['type'] == 1) {
		$s = true;
		$allgroups['staff'] = __('(all staff)');
		$allgroups[__('Secondary')] = null;
	}
	
	$allgroups[$group['id']] = $group['name'];
}

if (!$s) {
	$allgroups['staff'] = __('(all staff)');
	$s = true;
}


MakeCrumbs([actionLink("memberlist") => __("Member list")]);


$fields = [
	'sortBy' => makeSelect("sort", [
			"" => __("Post count"),
			"id" => __("ID"),
			"name" => __("Name"),
			"reg" => __("Registration date")
		]),
	'order' => makeSelect("order", [
			"desc" => __("Descending"),
			"asc" => __("Ascending"),
		]),
	'group' => makeSelect("group", $allgroups),
	'name' => '<input type="text" name="name" size=24 maxlength=20 value="'.htmlspecialchars($http->get('name')).'">',

	'btnSearch' => '<input type="submit" value="'.__('<i class=\"icon-search\"></i> Search').'">',
];

echo getForm('memberlist');

RenderTemplate('form_memberlist', ['fields' => $fields]);

echo '
	</form>';
	
	
$getArgs = [];
	
$tpp = $loguser['threadsperpage'];
if($tpp<1) $tpp=50;

if(isset($http->get('from')))
	$from = (int)$http->get('from');
else
	$from = 0;

if(isset($http->get('order'))) {
	$dir = $http->get('order');
	if($dir != "asc" && $dir != "desc")
		unset($dir);
	else
		$getArgs[] = 'order='.$dir;
}

$sort = $http->get('sort');
if(!in_array($sort, ['', 'id', 'name', 'reg']))
	unset($sort);

if ($sort)
	$getArgs[] = 'sort='.$sort;

$pow = null;
if($http->get('group') !== "none") {
	if ($http->get('group') === 'staff') {
		$pow = [];
		foreach ($usergroups as $g) {
			if ($g['display'] == 1)
				$pow[] = $g['id'];
		}
	} else
		$pow = (int)$http->get('group');

	if ($pow !== null)
		$getArgs[] = 'group='.$pow;
}

$order = "";

switch($sort) {
	case "id": $order = "id ".(isset($dir) ? $dir : "asc"); break;
	case "name": $order = "name ".(isset($dir) ? $dir : "asc"); break;
	case "reg": $order = "regdate ".(isset($dir) ? $dir : "desc"); break;
	default: $order="posts ".(isset($dir) ? $dir : "desc");
}

$where = '1';

if($pow !== null) {
	if (is_array($pow))
		$where .= " AND primarygroup IN ({2c})";
	else if ($usergroups[$pow]['type'] == 0)
		$where .= " AND primarygroup={2}";
	else
		$where .= " AND (SELECT COUNT(*) FROM {secondarygroups} sg WHERE sg.userid=id AND sg.groupid={2})>0";
}

$query = $http->get('name');

if($query != "") {
	$where.= " and (name like {3} or displayname like {3})";
	$getArgs[] = 'name='.urlencode($query);
}

$numUsers = FetchResult("select count(*) from {users} where ".$where, null, null, $pow, "%{$query}%");
$rUsers = Query("select * from {users} where ".$where." order by ".$order.", name asc limit {0u},{1u}", $from, $tpp, $pow, "%{$query}%");

$users = [];
$i = $from + 1;
while($user = Fetch($rUsers)) {
	$udata = [];

	$daysKnown = (time()-$user['regdate'])/86400;
	$udata['average'] = sprintf("%1.02f", $user['posts'] / $daysKnown);

	if($user['picture']) {
		$pic = str_replace('$root/', DATA_URL, $user['picture']);
		$udata['avatar'] = "<img src=\"".htmlspecialchars($pic)."\" alt=\"\" style=\"max-width: 60px;max-height:60px;\">";
	} else
		$udata['avatar'] = '';

	$udata['num'] = $i;

	$udata['link'] = UserLink($user);
	$udata['posts'] = $user['posts'];
	$udata['birthday'] = ($user['birthday'] ? cdate('M jS', $user['birthday']) : '');
	$udata['regdate'] = cdate('M jS Y', $user['regdate']);
	$udata['banlink'] = return pageLinkTag("<span$classing class=\"userlink\" title=\"$title\">$fname</span>", "banuser", [
			'id' => $user['id'],
			'name' => slugify($user['name'])
		]);

	$users[] = $udata;
	$i++;
}

$getArgs[] = 'from=';

$pagelinks = PageLinks(actionLink('memberlist', '', implode('&',$getArgs)), $tpp, $from, $numUsers);

RenderTemplate('memberlist', ['pagelinks' => $pagelinks, 'numUsers' => $numUsers, 'users' => $users]);


function makeSelect($name, $options) {
	$result = "<select name=\"".$name."\" id=\"".$name."\">";

	$i = 0;
	$hasgroups = false;
	foreach ($options as $key => $value) {
		if ($value == null) {
			if ($hasgroups) $result .= "\n\t</optgroup>";
			$result .= "\n\t<optgroup label=\"".$key."\">";
			$hasgroups = true;
			continue;
		}

		$result .= "\n\t<option".($key===$http->get($name) ? " selected=\"selected\"" : "")." value=\"".$key."\">".$value."</option>";
	}

	if ($hasgroups) $result .= "\n\t</optgroup>";
	$result .= "\n</select>";

	return $result;
}
