<?php

require('constants.inc.php');
require('config.inc.php');
require('functions.inc.php');

@mkdir('out/');


// setup rate limit, 5000 request per hour
$_TIME = microtime(true);
$_LIMIT = 5000/3600;


// connect to database
$db = new mysqli($conf['db']['host'], $conf['db']['user'], $conf['db']['pwd'], $conf['db']['name']);


// get standard data
$users = query2array('SELECT id, username FROM mantis_user_table;', 'id');
$projects = query2array('SELECT id, name FROM mantis_project_table WHERE id IN(' . implode(',', $conf['projects']) . ');', 'id');
$fields = query2array('SELECT id, name, default_value FROM mantis_custom_field_table WHERE id IN(' . implode(',', array_keys($conf['fields'])) . ');', 'id');
foreach ($fields as &$field)
{
  $field['key'] = $conf['fields'][$field['id']];
}
unset($field);


// storages for github ids
$github_issues = array();
$github_milestones = array();


// hardcoded labels
$github_labels = array(
  'resolution' => array(
    UNABLE_TO_DUPLICATE => 'unable to duplicate',
    NOT_FIXABLE => 'not fixable',
    DUPLICATE => 'duplicate',
    NOT_A_BUG => 'not a bug',
    SUSPENDED => 'suspended',
    WONT_FIX => 'wontfix',
    ),
  'severity' => array(
    FEATURE => 'feature',
    TRIVIAL => 'trivial',
    TEXT => 'text',
    TWEAK => 'tweak',
    MINOR => 'minor',
    MAJOR => 'major',
    CRASH => 'crash',
    BLOCK => 'block',
    ),
  'priority' => array(
    LOW => 'low priority',
    HIGH => 'high priority',
    URGENT => 'urgent',
    IMMEDIATE => 'immediate',
    ),
  );


// create milestones
if (!file_exists('out/milestones.json'))
{
  $query ='
SELECT
    id,
    version,
    released
  FROM mantis_project_version_table
  WHERE
    project_id IN(' . implode(',', $conf['projects']) . ')
  ORDER BY version ASC
;';

  $result = $db->query($query);

  while ($row = $result->fetch_assoc())
  {
    $milestone = array(
      'id' => $row['id'],
      'title' => $row['version'],
      'state' => $row['released']==1 ? 'closed' : 'open',
      );
      
    $resp = github_add_milestone($milestone);
    
    if (isset($resp['number']))
    {
      $github_milestones[$milestone['title']] = $resp['number'];
      logger('INFO', 'Imported milestone ' . $milestone['title'] . ' (#' . $resp['number'] .')');
    }
    else
    {
      logger('ERROR', 'Failed to import milestone ' . $milestone['title']);
    }
  }
  
  file_put_contents('out/milestones.json', json_encode($github_milestones));
}
else
{
  $github_milestones = json_decode(file_get_contents('out/milestones.json'), true);
}


// create bugs
$query = '
SELECT
    b.id,
    b.reporter_id,
    b.handler_id,
    b.priority,
    b.severity,
    b.reproducibility,
    b.status,
    b.resolution,
    b.category,
    b.date_submitted,
    b.version,
    b.fixed_in_version,
    b.target_version,
    b.summary,
    t.description,
    t.steps_to_reproduce,
    t.additional_information';

foreach ($fields as $field)
{
  $query.= ',
    f' . $field['id'] . '.value AS ' . $conf['fields'][$field['id']];
}
  
$query.= '
  FROM mantis_bug_table AS b
    INNER JOIN mantis_bug_text_table AS t
      ON b.bug_text_id = t.id';
  
foreach ($fields as $field)
{
  $query.= '
    LEFT JOIN mantis_custom_field_string_table AS f' . $field['id'] . '
      ON f' . $field['id'] . '.bug_id = b.id
      AND f' . $field['id'] . '.field_id = ' . $field['id'] . '
      AND f' . $field['id'] . '.value != \'' . $db->real_escape_string($field['default_value']) . '\'';
}
  
$query.= '
  WHERE
    b.project_id IN(' . implode(',', $conf['projects']) . ')
    AND b.duplicate_id = 0
    ' . (!$conf['import_resolved'] ? 'AND b.status < ' . RESOLVED : '') . '
    ' . (!$conf['import_private'] ? 'AND b.view_state != ' . VS_PRIVATE : '') . '
  ORDER BY
    b.date_submitted ASC
;';

$result = $db->query($query);

while ($row = $result->fetch_assoc())
{
  $issue = array(
    'id' => $row['id'],
    'title' => get_issue_title($row),
    'body' => get_issue_body($row),
    'assignee' => get_issue_assignee($row),
    'milestone' => get_issue_milestone($row),
    'labels' => get_issue_labels($row),
    );
    
  $resp = github_add_issue($issue, get_github_user($row));
  
  if (isset($resp['number']))
  {
    $github_issues[$issue['id']] = $resp['number'];
    
    logger('INFO', 'Imported ticket #' . $issue['id'] . ' (#' . $resp['number'] . ')');
    
    // close bug
    if ($row['status'] >= RESOLVED)
    {
      $resp2 = github_update_issue(
        $resp['number'],
        array('state' => 'closed'),
        get_github_user($row)
        );
      
      if (isset($resp2['number']))
      {
        logger('INFO', 'Closed ticket #' . $issue['id'] . ' (#' . $resp['number'] . ')');
      }
      else
      {
        logger('ERROR', 'Failed to close ticket #' . $issue['id'] . ' (#' . $resp['number'] .')');
      }
    }
  }
  else
  {
    logger('ERROR', 'Failed to import ticket #' . $issue['id']);
  }
}

file_put_contents('out/issues.json', json_encode($github_issues));


// create bug relationships via comments
$query = '
SELECT
    source_bug_id AS src,
    destination_bug_id AS dest,
    relationship_type AS type
  FROM mantis_bug_relationship_table
  WHERE
    relationship_type IN(' . implode(',', array(BUG_RELATED, BUG_DEPENDANT, BUG_BLOCKS)) . ')
    AND source_bug_id IN(' . implode(',', array_keys($github_issues)) . ')
    AND destination_bug_id IN(' . implode(',', array_keys($github_issues)) . ')
  ORDER BY src
;';

$result = $db->query($query);

while ($row = $result->fetch_assoc())
{
  $resp = github_add_comment(
    $github_issues[$row['src']],
    get_related_comment($row),
    $conf['users'][$conf['default_user']]
    );
  
  if (isset($resp['id']))
  {
    logger('INFO', 'Added relationship between tickets #' . $row['src'] . ' and #' . $row['dest']);
  }
  else
  {
    logger('ERROR', 'Failed to add relationship between tickets #' . $row['src'] . ' and #' . $row['dest']);
  }
}


// create bug notes
$query = '
SELECT
    n.id,
    n.bug_id,
    n.reporter_id,
    n.date_submitted,
    t.note
  FROM mantis_bugnote_table AS n
    INNER JOIN mantis_bugnote_text_table AS t
      ON n.bugnote_text_id = t.id
  WHERE
    n.note_type = ' . BUGNOTE . '
    AND n.bug_id IN(' . implode(',', array_keys($github_issues)) . ')
    AND n.reporter_id NOT IN(' . implode(',', $conf['bugnote_ban_users']) . ')
  ORDER BY
    n.bug_id ASC,
    n.date_submitted ASC
;';

$result = $db->query($query);

while ($row = $result->fetch_assoc())
{
  $resp = github_add_comment(
    $github_issues[$row['bug_id']],
    get_comment_body($row),
    get_github_user($row)
    );
  
  if (isset($resp['id']))
  {
    logger('INFO', 'Imported comment #' . $row['id'] . ' on ticket #' . $row['bug_id'] . ' (#' . $github_issues[$row['bug_id']] . ')');
  }
  else
  {
    logger('ERROR', 'Failed to import comment #' . $row['id'] . ' on ticket #' . $row['bug_id']);
  }
}
