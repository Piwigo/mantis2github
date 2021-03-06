<?php

require('constants.inc.php');
require('config.inc.php');
require('functions.inc.php');


//-----------------------------------------------------------------------------\
// INIT                                                                        |
//-----------------------------------------------------------------------------/
@mkdir('out/');

// setup rate limit, 600 request per hour
$_TIME = time();
$_LIMIT = 3600/600;

// connect to database
$db = new mysqli($conf['db']['host'], $conf['db']['user'], $conf['db']['pwd'], $conf['db']['name']);

// get standard data
$users = query2array('SELECT id, username FROM mantis_user_table;', 'id');
$projects = query2array('SELECT id, name FROM mantis_project_table WHERE id IN(' . implode(',', $conf['projects']) . ');', 'id');
$fields = empty($conf['fields']) ? array() : query2array('SELECT id, name, default_value FROM mantis_custom_field_table WHERE id IN(' . implode(',', array_keys($conf['fields'])) . ');', 'id');
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


//-----------------------------------------------------------------------------\
// MILESTONES                                                                  |
//-----------------------------------------------------------------------------/
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


//-----------------------------------------------------------------------------\
// ISSUES                                                                      |
//-----------------------------------------------------------------------------/
if (file_exists('out/issues.txt'))
{
  foreach (file('out/issues.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line)
  {
    $line = explode(' ', $line);
    $github_issues[$line[0]] = $line[1];
  }
}
else
{
  file_put_contents('out/issues.txt', 'mantis_id github_id' . "\n", FILE_APPEND);
}

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
    c.name as category,
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
      ON b.bug_text_id = t.id
    LEFT JOIN mantis_category_table AS c
      ON c.id = b.category_id';
  
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
    ' . (!$conf['import_private'] ? 'AND b.view_state != ' . VS_PRIVATE : '');

if (!empty($github_issues))
{
  $query.= '
    AND b.id NOT IN (' . implode(',', array_keys($github_issues)) . ')';
}

$query.= '
  ORDER BY
    b.date_submitted ASC
;';

$result = $db->query($query);

while ($row = $result->fetch_assoc())
{
  $user = get_github_user($row);
  
  $issue = array(
    'id' => $row['id'],
    'title' => get_issue_title($row),
    'body' => get_issue_body($row),
    'assignee' => get_issue_assignee($row),
    'milestone' => get_issue_milestone($row),
    'labels' => get_issue_labels($row),
    );
    
  $resp = github_add_issue($issue, $user);
  
  if (isset($resp['number']))
  {
    $github_issues[$issue['id']] = $resp['number'];
    
    logger('INFO', 'Imported ticket #' . $issue['id'] . ' (#' . $resp['number'] . ')');
    
    file_put_contents('out/issues.txt', $issue['id'] . ' ' . $resp['number'] . "\n", FILE_APPEND);
    
    // close bug
    if ($row['status'] >= RESOLVED)
    {
      $resp2 = github_update_issue(
        $resp['number'],
        array('state' => 'closed'),
        $user
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


//-----------------------------------------------------------------------------\
// RELATIONSHIPS (via comments)                                                |
//-----------------------------------------------------------------------------/
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


//-----------------------------------------------------------------------------\
// COMMENTS                                                                    |
//-----------------------------------------------------------------------------/
$comments_id = array();
if (file_exists('out/comments.txt'))
{
  $comments_id = file('out/comments.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

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
    ' . (!empty($conf['bugnote_ban_users']) ? 'AND n.reporter_id NOT IN(' . implode(',', $conf['bugnote_ban_users']) . ')' : '');

if (!empty($comments_id))
{
  $query.= '
    AND n.id NOT IN (' . implode(',', $comments_id) . ')';
}

$query.= '
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
    file_put_contents('out/comments.txt', $row['id'] . "\n", FILE_APPEND);
    
    logger('INFO', 'Imported comment #' . $row['id'] . ' on ticket #' . $row['bug_id'] . ' (#' . $github_issues[$row['bug_id']] . ')');
  }
  else
  {
    logger('ERROR', 'Failed to import comment #' . $row['id'] . ' on ticket #' . $row['bug_id']);
  }
}
