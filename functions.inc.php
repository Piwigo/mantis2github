<?php

/**
 * Display log message and write it in out/log.txt file
 *
 * @param string level
 * @param string message
 */
function logger($level, $message)
{
  echo $level . ' ' . $message . "\n";
  
  file_put_contents('out/log.txt', $level . ' ' . $message . "\n", FILE_APPEND);
}

/**
 * Builds an data array from a SQL query.
 *
 * @param string $query
 * @param string $key_name
 * @param string $value_name
 * @return array
 */
function query2array($query, $key_name=null, $value_name=null)
{
  global $db;
  
  $result = $db->query($query);
  $data = array();

  if (isset($key_name))
  {
    if (isset($value_name))
    {
      while ($row = $result->fetch_assoc())
        $data[ $row[$key_name] ] = $row[$value_name];
    }
    else
    {
      while ($row = $result->fetch_assoc())
        $data[ $row[$key_name] ] = $row;
    }
  }
  else
  {
    if (isset($value_name))
    {
      while ($row = $result->fetch_assoc())
        $data[] = $row[$value_name];
    }
    else
    {
      while ($row = $result->fetch_assoc())
        $data[] = $row;
    }
  }

  return $data;
}

/**
 * Get Mantis username from global $users array
 *
 * @param string id
 * @param strinf default
 * @return string
 */
function get_username($id, $def='unknown user')
{
  global $users;
  
  if (isset($users[$id]))
  {
    return $users[$id]['username'];
  }
  else
  {
    return $def;
  }
}

/**
 * Build issue title
 *
 * @param array issue
 * @return string
 */
function get_issue_title($issue)
{
  global $conf;
  
  $res = '';
  
  if (!empty($issue['category']))
  {
    $res.= '[' . $issue['category'] . '] ';
  }
  
  $res.= $issue['summary'];
  
  //$res = htmlspecialchars($res, ENT_QUOTES, $conf['db']['encode'], false);
  $res = utf8_encode($res);
  
  return $res;
}

/**
 * Build issue body
 *
 * @param array issue
 * @return string
 */
function get_issue_body($issue)
{
  global $fields, $conf;
  
  $res = '**Reported by ' . get_username($issue['reporter_id']) . ' on ' .
    (new DateTime('@'.$issue['date_submitted']))->format('j M Y H:i') . '**' . "\n\n";
  
  if (!empty($issue['version']))
  {
    $res.= '**Version:** ' . $issue['version'] . "\n";
  }
  
  foreach ($fields as $field)
  {
    if (!empty($issue[$field['key']]))
    {
      $res.= '**' . $field['name'] . ':** ' . $issue[$field['key']] . "\n";
    }
  }
  
  if (!empty($issue['description']))
  {
    $res.= "\n" . $issue['description'] . "\n";
  }
  
  if (!empty($issue['steps_to_reproduce']))
  {
    $res.= "\n" . '**Steps to reproduce:** ' . $issue['steps_to_reproduce'] . "\n";
  }
  
  if (!empty($issue['additional_information']))
  {
    $res.= "\n" . '**Additional information:** ' . $issue['additional_information'] . "\n";
  }
  
  $res = htmlspecialchars($res, ENT_QUOTES, $conf['db']['encode'], false);
  $res = utf8_encode($res);
  
  if ($conf['backlink']['enable'])
  {
    $res.= "\n" . '[' . sprintf($conf['backlink']['title'], $issue['id']) .
      '](' . sprintf($conf['backlink']['url'], $issue['id']) . ')';
  }
  
  return $res;
}

/**
 * Build comment
 *
 * @param array note
 * @return string
 */
function get_comment_body($note)
{
  global $conf;
  
  $res = '**Comment posted by ' . get_username($note['reporter_id']) . ' on ' .
    (new DateTime('@'.$note['date_submitted']))->format('j M Y H:i') . '**' . "\n\n";
  
  $res.= $note['note'];
  
  $res = htmlspecialchars($res, ENT_QUOTES, $conf['db']['encode'], false);
  $res = utf8_encode($res);
  
  return $res;
}

/**
 * Get issue assignee username among Github users
 *
 * @param array issue
 * @return string|null
 */
function get_issue_assignee($issue)
{
  global $conf;
  
  if ($issue['handler_id'] == 0)
  {
    return null;
  }
  
  $user = get_username($issue['handler_id'], null);
  
  if ($user !== null && isset($conf['users'][$user]))
  {
    return $conf['users'][$user]['login'];
  }
  
  if ($conf['assign_to_default_user'])
  {
    return $conf['users'][$conf['default_user']]['login'];
  }
  
  return null;
}

/**
 * Get issue milestone id
 *
 * @param array issue
 * @return int
 */
function get_issue_milestone($issue)
{
  global $github_milestones;
  
  if (!empty($issue['fixed_in_version']))
  {
    $ret = $issue['fixed_in_version'];
  }
  else if (!empty($issue['target_version']))
  {
    $ret = $issue['target_version'];
  }
  else
  {
    return null;
  }
  
  if (isset($github_milestones[$ret]))
  {
    return $github_milestones[$ret];
  }
  else
  {
    return null;
  }
}

/**
 * Get issue labels
 *
 * @param array issue
 * @return string[]
 */
function get_issue_labels($issue)
{
  global $github_labels;
  
  $labels = array();
  
  foreach (array('resolution', 'severity', 'priority') as $field)
  {
    if (isset($github_labels[$field][$issue[$field]]))
    {
      $labels[] = $github_labels[$field][$issue[$field]];
    }
  }
  
  return $labels;
}

/**
 * Get Github user for API call
 *
 * @param array issue or note
 * @return array
 */
function get_github_user($issue)
{
  global $conf;
  
  if (isset($issue['reporter_id']))
  {
    $user = get_username($issue['reporter_id'], null);
    
    if ($user !== null)
    {
      if (isset($conf['users'][$user]['pwd']))
      {
        return $conf['users'][$user];
      }
    }
  }
  
  if (isset($issue['handler_id']))
  {
    $user = get_username($issue['handler_id'], null);
    
    if ($user !== null)
    {
      if (isset($conf['users'][$user]['pwd']))
      {
        return $conf['users'][$user];
      }
    }
  }
  
  return $conf['users'][$conf['default_user']];
}

/**
 * Get comment body for relation
 *
 * @param array relation
 * @return string
 */
function get_related_comment($rel)
{
  global $github_issues;
  
  $id = $github_issues[$rel['dest']];
  
  switch ($rel['type'])
  {
  case BUG_RELATED:
    return 'Related to #' . $id;
  case BUG_DEPENDANT:
    return 'Depends on #' . $id;
  case BUG_BLOCKS:
    return 'Blocks #' . $id;
  }
}

/**
 * Perform POST/PATCH on Github API.
 *
 * @param string url
 * @param array data
 * @param array user
 * @param boolean patch
 * @return array
 */
function github_post($url, $data, $user, $patch = false)
{
  global $_TIME, $_LIMIT, $conf;
  
  $now = time();
  if ($now - $_TIME < $_LIMIT)
  {
    sleep(round($_LIMIT - ($now - $_TIME)));
  }
  $_TIME = time();
  
  
  $url = 'https://api.github.com/repos/' . $conf['repo']['user'] .'/' . $conf['repo']['name'] . '/' . $url;

  file_put_contents('out/dump.txt', $url . "\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_USERPWD, $user['login'] . ':' . $user['pwd']);
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_USERAGENT, 'mantis2github for ' . $conf['repo']['user'] .'/' . $conf['repo']['name']);
  if ($patch)
  {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
  }
  
  $ret = curl_exec($ch);
  if (!$ret)
  {
    trigger_error(curl_error($ch));
  }
  curl_close($ch);
  
  file_put_contents('out/dump.txt', $url . "\n" . json_encode(json_decode($ret, true), JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
  
  return $ret;
}

/**
 * Add a milestone via GH API
 *
 * @param array data
 * @return array
 */
function github_add_milestone($data)
{
  global $conf;
  $user = $conf['users'][$conf['default_user']];
  $res = github_post('milestones', $data, $user);
  return json_decode($res, true);
}

/**
 * Add a label via GH API
 *
 * @param array data
 * @return array
 */
function github_add_label($data)
{
  global $conf;
  $user = $conf['users'][$conf['default_user']];
  $res = github_post('labels', $data, $user);
  return json_decode($res, true);
}

/**
 * Add an issue via GH API
 *
 * @param array data
 * @param array user
 * @return array
 */
function github_add_issue($data, $user)
{
  $res = github_post('issues', $data, $user);
  return json_decode($res, true);
}

/**
 * Add a comment via GH API
 *
 * @param int issue
 * @param array data
 * @param array user
 * @return array
 */
function github_add_comment($issue, $data, $user)
{
  $res = github_post('issues/' . $issue . '/comments', array('body' => $data), $user);
  return json_decode($res, true);
}

/**
 * Update an issue via GH API
 *
 * @param int issue
 * @param array data
 * @param array user
 * @return array
 */
function github_update_issue($issue, $data, $user)
{
  $res = github_post('issues/' . $issue, $data, $user, true);
  return json_decode($res, true);
}
