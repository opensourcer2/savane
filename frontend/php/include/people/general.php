<?php
# <one line to give a brief idea of what this does.>
# 
# Copyright 1999-2000 (c) The SourceForge Crew
# Copyright 2004 (c) Mathieu Roy <yeupou--gnu.org>
# Copyright 2013 (c) Ineiev <ineiev--gnu.org>
#
# This file is part of Savane.
# 
# Savane is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
# 
# Savane is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
# 
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.


# This file contains all the functions for the people module
# Since all three files orginial were included by pre.php
# it just makes sense to wrap them all up into this

# category.php

function people_get_type_name($type_id)
{
  if (!ctype_digit($type_id))
    return 'Invalid ID';
  $result = db_execute("SELECT group_type.name
                        FROM group_type WHERE type_id = ?",
                        array($type_id));
  if (!$result || db_numrows($result) < 1)
    {
      return 'Invalid ID';
    }
  return db_result($result,0,'name');
}

function people_get_category_name($category_id)
{
  if (!ctype_digit($category_id))
    return 'Invalid ID';
  $result = db_execute("SELECT name FROM people_job_category WHERE category_id=?",
		       array($category_id));
  if (!$result || db_numrows($result) < 1)
    {
      return 'Invalid ID';
    }
  else
    {
      return db_result($result,0,'name');
    }
}

# Show job selection controls.
function people_show_table()
{
  $return = '<h3>'._("Category").'</h3>';
  $form_is_empty = 1;

  $result = db_query("SELECT * FROM people_job_category ORDER BY category_id");
  $rows=db_numrows($result);
  if (!$result || $rows < 1)
    {
      $return .= '<p><strong>'._("No Categories Found").'</strong></p>';
    }
  else
    {
      $form_is_empty = 0;
      for ($i=0; $i<$rows; $i++)
        {
          $count_res=db_execute("SELECT count(*) AS count FROM people_job
                                 WHERE category_id=? AND status_id=1",
                                 array(db_result($result,$i,'category_id')));
          print db_error();
          $return .= '<input type="checkbox" name="categories[]" value="'.
             db_result($result,$i,'category_id') .'"><a href="'.
             $_SERVER["PHP_SELF"].'?categories[]='.
             db_result($result,$i,'category_id').'">'.
          db_result($result,$i,'name') .' ('.
             db_result($count_res,0,'count') .')</a><br />
';
        }
    }

  $return .= '
<h3>'._("Project type").'</h3>';
  $result=db_query("SELECT group_type.type_id, group_type.name,
                    COUNT(people_job.job_id) AS count FROM
                    group_type JOIN (groups JOIN people_job ON
                                     groups.group_id = people_job.group_id)
                    ON group_type.type_id = groups.type
                    WHERE status_id = 1 GROUP BY type_id ORDER BY type_id");
  $rows=db_numrows($result);
  if (!$result || $rows < 1)
    {
      $return .= '<p><strong>'._("No Categories Found").'</strong></p>';
    }
  else
    {
      $form_is_empty = 0;

      for ($i=0; $i<$rows; $i++)
        {
          $return .= '<input type="checkbox" name="types[]" value="' .
             db_result($result,$i,'type_id') . '"><a href="'.
             $_SERVER["PHP_SELF"].'?types[]='. db_result($result,$i,'type_id').
             '">' .  db_result($result,$i,'name') . ' ('.
             db_result($result,$i,'count'). ')</a><br />
';
        }
    }
  if(!$form_is_empty)
    {
      $return = '<form action="'.$_SERVER["PHP_SELF"].'" method="get">
' . $return . '
<hr /><input type="checkbox" name="show_any" value="1">
Show all jobs for all project types<br />
<input type="submit" name="submit" value="'._("Search").'" />
</form>
';
    }
  return $return;
}

function people_show_category_list()
{

  #show a list of categories
  #provide links to drill into a detail page that shows these categories

  $sql="SELECT * FROM people_job_category ORDER BY category_id";
  $result=db_query($sql);
  $rows=db_numrows($result);
  $return = '';
  if (!$result || $rows < 1)
    {
      $return .= _("No Categories Found");
    }
  else
    {
      $j = 0;
      for ($i=0; $i<$rows; $i++)
	{
	  $count_res=db_execute("SELECT count(*) AS count FROM people_job WHERE category_id=? AND status_id=1",
				array(db_result($result,$i,'category_id')));

	  # Print only if there are result within the category
	  if (db_result($count_res,0,'count') > 0)
	    {
	      $j++;
	      $return .= '<li class="'.utils_get_alt_row_color($j).'"><span class="smaller">&nbsp;&nbsp;- <a href="'.$GLOBALS['sys_home'].'people/?category_id='.
		db_result($result,$i,'category_id') .'">'.db_result($count_res,0,'count').
		' '.db_result($result,$i,'name') .'</a></span></li>';
	    }
	}
    }
  if (!$return)
    { return false; }

  return "<ul class=\"boxli\">".$return."</ul>";
}

# jobs.php

function people_job_status_box($name='status_id',$checked='xyxy')
{
  $sql="SELECT * FROM people_job_status";
  $result=db_query($sql);
  return html_build_select_box ($result,$name,$checked);
}

function people_job_category_box($name='category_id',$checked='xyxy')
{
  $sql="SELECT * FROM people_job_category";
  $result=db_query($sql);
  return html_build_select_box ($result,$name,$checked);
}

function people_add_to_job_inventory($job_id,$skill_id,$skill_level_id,$skill_year_id)
{
  global $feedback;
  if (user_isloggedin())
    {
      #check if they've already added this skill
      $result = db_execute("SELECT * FROM people_job_inventory WHERE job_id=? AND skill_id=?",
                           array($job_id, $skill_id));
      if (!$result || db_numrows($result) < 1)
	{
	  #skill isn't already in this inventory
          $result = db_autoexecute('people_job_inventory',
	    array(
              'job_id' => $job_id,
	      'skill_id' => $skill_id,
	      'skill_level_id' => $skill_level_id,
	      'skill_year_id' => $skill_year_id
            ), DB_AUTOQUERY_INSERT);
	  if (!$result || db_affected_rows($result) < 1)
	    {
	      ' ERROR inserting into skill inventory ';
	      print db_error();
	    }
	  else
	    {
	      fb(_("Added to skill inventory "));
	    }
	}
      else
	{
	  fb(_("ERROR - skill already in your inventory "));
	}

    }
  else
    {
      print '<h1>'._("You must be logged in first").'</h1>';
    }
}

function people_show_job_inventory($job_id)
{
  $result = db_execute("SELECT people_skill.name AS skill_name, people_skill_level.name AS level_name, people_skill_year.name AS year_name ".
     "FROM people_skill_year,people_skill_level,people_skill,people_job_inventory ".
     "WHERE people_skill_year.skill_year_id=people_job_inventory.skill_year_id ".
     "AND people_skill_level.skill_level_id=people_job_inventory.skill_level_id ".
     "AND people_skill.skill_id=people_job_inventory.skill_id ".
     "AND people_job_inventory.job_id=?", array($job_id));

  $title_arr=array();
  $title_arr[]=_("Skill");
  $title_arr[]=_("Level");
  $title_arr[]=_("Experience");

  print html_build_list_table_top ($title_arr);

  $rows=db_numrows($result);
  if (!$result )  {
    print '<tr><td><p class="warn">('._("SQL Error:").')</p>';
    print db_error();
    print '</td></tr>';
  } else if ( $rows < 1)
    {
      print '<tr><td><p class="warn">('._("No Skill Inventory Set Up").')</p>';
      print '</td></tr>';
    }
  else
    {
      for ($i=0; $i < $rows; $i++)
	{
	  print '
			<tr class="'. utils_get_alt_row_color($i) .'">
				<td>'.db_result($result,$i,'skill_name').'</td>
				<td>'.db_result($result,$i,'level_name').'</td>
				<td>'.db_result($result,$i,'year_name').'</td></tr>';

	}
    }
  print '
		</table>';
}

function people_verify_job_group($job_id,$group_id)
{
  $result = db_execute("SELECT * FROM people_job WHERE job_id=? AND group_id=?",
		       array($job_id, $group_id));
  if (!$result || db_numrows($result) < 1)
    {
      return false;
    }
  else
    {
      return true;
    }
}

function people_edit_job_inventory($job_id,$group_id)
{
  $result = db_execute("SELECT *,people_skill.name AS skill_name
     FROM people_job_inventory,people_skill
     WHERE job_id = ? AND people_skill.skill_id=people_job_inventory.skill_id",
    array($job_id));

  $title_arr=array();
  $title_arr[]='Skill';
  $title_arr[]='Level';
  $title_arr[]='Experience';
  $title_arr[]='Action';

  print html_build_list_table_top ($title_arr);

  $rows = db_numrows($result);
  $i = 0;
  if (!$result || $rows < 1)
    {
      print '
			<tr><td colspan="4"><h2>'._("No Skill Inventory Set Up").'</h2></td></tr>';
      print db_error();
    }
  else
    {
      for (; $i < $rows; $i++)
	{
	  print '
			<form action="'.$_SERVER['PHP_SELF'].'" method="POST">
			<input type="HIDDEN" name="job_inventory_id" value="'. db_result($result,$i,'job_inventory_id') .'" />
			<input type="HIDDEN" name="job_id" value="'. db_result($result,$i,'job_id') .'" />
			<input type="HIDDEN" name="group_id" value="'.$group_id.'" />
			<tr class="'. utils_get_alt_row_color($i) .'">
				<td><span class="smaller">'. db_result($result,$i,'skill_name') . '</span></td>
				<td><span class="smaller">'. people_skill_level_box('skill_level_id',db_result($result,$i,'skill_level_id')). '</span></td>
				<td><span class="smaller">'. people_skill_year_box('skill_year_id',db_result($result,$i,'skill_year_id')). '</span></td>
				<td nowrap><span class="smaller"><input type="SUBMIT" name="update_job_inventory" '
	    .'value="'._("Update").'"> &nbsp;
					<input type="SUBMIT" name="delete_from_job_inventory" value="'
	    ._("Delete").'"></span></td>
				</tr></form>';
	}

    }
  #add a new skill
  $i++; #for row coloring

  print '
	<tr><td colspan="4"><h3>'._("Add A New Skill").'</h3></td></tr>
	<form action="'.$_SERVER['PHP_SELF'].'" method="POST">
	<input type="HIDDEN" name="job_id" value="'. $job_id .'" />
	<input type="HIDDEN" name="group_id" value="'.$group_id.'" />
	<tr class="'. utils_get_alt_row_color($i) .'">
		<td><span class="smaller">'. people_skill_box('skill_id'). '</span></td>
		<td><span class="smaller">'. people_skill_level_box('skill_level_id'). '</span></td>
		<td><span class="smaller">'. people_skill_year_box('skill_year_id'). '</span></td>
		<td nowrap><span class="smaller"><input type="SUBMIT" name="add_to_job_inventory"
		value="'._("Add Skill").'"></span></td>
	</tr></form>';

  print '
		</table>';
}

function people_show_job_list($result, $edit=0)
{
  global $sys_datefmt;
  #takes a result set from a query and shows the jobs

  #query must contain 'group_id', 'job_id', 'title', 'category_name', 'status_name' and 'type'

  $title_arr=array();
  $title_arr[]=_("Title");
  $title_arr[]=_("Category");
  $title_arr[]=_("Date Opened");
  $title_arr[]=_("Project");
  $title_arr[]=_("Type");

  $return = '';
  $return .= html_build_list_table_top ($title_arr);

  $rows=db_numrows($result);
  if ($rows < 1)
    {
      $return .= '<tr><td colspan="3"><h2>'._("None found").'</h2>'. db_error() .'</td></tr>';
    }
  else
    {
      for ($i=0; $i < $rows; $i++)
	{
	  # get type infos
	  $res_type = db_execute("SELECT name FROM group_type WHERE type_id=?",
				 array(db_result($result, $i, 'type')));

	  if ($edit)
	    {
	      $page = 'editjob.php';
	    }
	  else
	    {
	      $page = 'viewjob.php';
	    }
	  $return .= '
				<tr class="'. utils_get_alt_row_color($i) .
	     '"><td><a href="'.$GLOBALS['sys_home'].'people/'.$page.'?group_id='.
	     db_result($result,$i,'group_id') .'&job_id='.
	     db_result($result,$i,'job_id') .'">'.
	     db_result($result,$i,'title') .'</a></td><td>'.
	     db_result($result,$i,'category_name') .'</td><td>'.
	     utils_format_date(db_result($result,$i,'date')) .
	     '</td><td><a href="'.$GLOBALS['sys_home'].'projects/'.strtolower(db_result($result,$i,'unix_group_name')).'/">'.
	     db_result($result,$i,'group_name') .'</a></td><td>' .
	     db_result($res_type,0,'name') . '</td></tr>';
	}
    }

  $return .= '</table>';

  return $return;
}

function people_show_project_jobs($group_id,$edit=0)
{
  #show open jobs for this project
  $result = db_execute("SELECT people_job.group_id,people_job.job_id,groups.group_name,groups.unix_group_name,groups.type,people_job.title,people_job.date,people_job_category.name AS category_name ".
     "FROM people_job,people_job_category,groups ".
     "WHERE people_job.group_id=? ".
     "AND people_job.group_id=groups.group_id ".
     "AND people_job.category_id=people_job_category.category_id ".
     "AND people_job.status_id=1 ORDER BY date DESC", array($group_id));

  return people_show_job_list($result,$edit);
}

function people_project_jobs_rows($group_id)
{
  #show open jobs for this project
  $result = db_execute("SELECT people_job.group_id,people_job.job_id,groups.group_name,people_job.title,people_job.date,people_job_category.name AS category_name ".
     "FROM people_job,people_job_category,groups ".
     "WHERE people_job.group_id=? ".
     "AND people_job.group_id=groups.group_id ".
     "AND people_job.category_id=people_job_category.category_id ".
     "AND people_job.status_id=1 ORDER BY date DESC", array($group_id));
  $rows = db_numrows($result);
  return $rows;
}

# Show open jobs for the given job categories and types of projects,
# or all open jobs when $show_any is true.
function people_show_jobs($categories, $types, $show_any)
{
  #show open jobs for this category
  $category_ids = '';
  $type_ids = '';
  $sql_args = array();
  if (!$show_any)
    {
      $n = 0;
      if(count($categories))
        {
          $category_ids = 'AND ( people_job.category_id = ? ';
          $sql_args[$n++] = $categories[0];

          for ($i = 1; $i < count($categories); ++$i)
            {
              $category_ids .= 'OR people_job.category_id = ? ';
              $sql_args[$n++] = $categories[$i];
            }
          $category_ids .= ')';
        }
      if(count($types))
        {
          $type_ids = 'AND ( groups.type = ? ';
          $sql_args[$n++] = $types[0];

          for ($i = 1; $i < count($types); ++$i)
            {
              $type_ids .= 'OR groups.type = ? ';
              $sql_args[$n++] = $types[$i];
            }
          $type_ids .= ')';
        }
    }
  $result = db_execute("SELECT people_job.group_id,people_job.job_id,
     groups.unix_group_name,groups.group_name,groups.type,
     people_job.title,people_job.date,
     people_job_category.name AS category_name
     FROM (people_job JOIN people_job_category ON
           people_job.category_id=people_job_category.category_id)
       JOIN groups ON people_job.group_id=groups.group_id
     WHERE  groups.is_public = 1 AND people_job.status_id=1 ".
     $category_ids . $type_ids . " ORDER BY date DESC", $sql_args);
  return people_show_job_list($result);
}

# skill.php

function people_skill_box($name='skill_id',$checked='xyxy')
{
  global $PEOPLE_SKILL;
  if (!$PEOPLE_SKILL)
    {
      #will be used many times potentially on a single page
      $sql="SELECT * FROM people_skill ORDER BY name ASC";
      $PEOPLE_SKILL=db_query($sql);
    }
  return html_build_select_box ($PEOPLE_SKILL,$name,$checked);
}

function people_skill_level_box($name='skill_level_id',$checked='xyxy')
{
  global $PEOPLE_SKILL_LEVEL;
  if (!$PEOPLE_SKILL_LEVEL)
    {
      #will be used many times potentially on a single page
      $sql="SELECT * FROM people_skill_level";
      $PEOPLE_SKILL_LEVEL=db_query($sql);
    }
  return html_build_select_box ($PEOPLE_SKILL_LEVEL,$name,$checked);
}

function people_skill_year_box($name='skill_year_id',$checked='xyxy')
{
  global $PEOPLE_SKILL_YEAR;
  if (!$PEOPLE_SKILL_YEAR)
    {
      #will be used many times potentially on a single page
      $sql="SELECT * FROM people_skill_year";
      $PEOPLE_SKILL_YEAR=db_query($sql);
    }
  return html_build_select_box ($PEOPLE_SKILL_YEAR,$name,$checked);
}

function people_add_to_skill_inventory($skill_id,$skill_level_id,$skill_year_id)
{
  global $feedback;
  if (user_isloggedin())
    {
      #check if they've already added this skill
      $result = db_execute("SELECT * FROM people_skill_inventory WHERE user_id=? AND skill_id=?",
                           array(user_getid(), $skill_id));
      if (!$result || db_numrows($result) < 1)
	{
	  #skill not already in inventory
	  $result=db_autoexecute('people_skill_inventory',
            array(
              'user_id' => user_getid(),
              'skill_id' => $skill_id,
              'skill_level_id' => $skill_level_id,
              'skill_year_id' => $skill_year_id
            ), DB_AUTOQUERY_INSERT);
	  if (!$result || db_affected_rows($result) < 1)
	    {
	      ' ERROR inserting into skill inventory ';
	      print db_error();
	    }
	  else
	    {
	      ' Added to skill inventory ';
	    }
	}
      else
	{
	  ' ERROR - skill already in your inventory ';
	}
    }
  else
    {
      print '<H1>You must be logged in first</H1>';
    }
}

function people_show_skill_inventory($user_id)
{
  $result = db_execute("SELECT people_skill.name AS skill_name, people_skill_level.name AS level_name, people_skill_year.name AS year_name ".
     "FROM people_skill_year,people_skill_level,people_skill,people_skill_inventory ".
     "WHERE people_skill_year.skill_year_id=people_skill_inventory.skill_year_id ".
     "AND people_skill_level.skill_level_id=people_skill_inventory.skill_level_id ".
     "AND people_skill.skill_id=people_skill_inventory.skill_id ".
     "AND people_skill_inventory.user_id=?", array($user_id));

  $title_arr=array();
  $title_arr[]=_("Skill");
  $title_arr[]=_("Level");
  $title_arr[]=_("Experience");

  print html_build_list_table_top ($title_arr);

  $rows=db_numrows($result);
  if (!$result )  {
    print '<tr><td><p class="warn">('._("SQL Error:").')</p>';
    print db_error();
    print '</td></tr>';
  } else if ( $rows < 1)
    {
      print '<tr><td><p class="warn">('._("No Skill Inventory Set Up").')</p>';
      print '</td></tr>';
    }
  else
    {
      for ($i=0; $i < $rows; $i++)
	{
	  print '
			<tr class="'. utils_get_alt_row_color($i) .'">
				<td>'.db_result($result,$i,'skill_name').'</td>
				<td>'.db_result($result,$i,'level_name').'</td>
				<td>'.db_result($result,$i,'year_name').'</td></tr>';
	}
    }
  print '</table>';
}

function people_edit_skill_inventory($user_id)
{
  $result = db_execute("SELECT *,people_skill.name AS skill_name FROM people_skill_inventory,people_skill WHERE user_id=? and people_skill.skill_id=people_skill_inventory.skill_id", array($user_id));

  $title_arr=array();
  $title_arr[]=_("Skill");
  $title_arr[]=_("Level");
  $title_arr[]=_("Experience");
  $title_arr[]=_("Action");

  print html_build_list_table_top ($title_arr);

  $i = 0;
  $rows=db_numrows($result);
  if (!$result )  {
    print '<tr><td colspan="4"><p class="warn">('._("SQL Error:").')';
    print db_error();
    print '</p></td></tr>';
  } else if ( $rows < 1)
    {
      print '<tr><td colspan="4"><p class="warn">('._("No Skill Inventory Set Up").')</p>';
      print '</td></tr>';
    }
  else
    {
      for ($i=0; $i < $rows; $i++)
	{
	  print '
			<form action="'.$_SERVER['PHP_SELF'].'" method="POST">
			<input type="hidden" name="skill_inventory_id" value="'.db_result($result,$i,'skill_inventory_id').'" />
			<tr class="'. utils_get_alt_row_color($i) .'">
				<td><span class="smaller">'. db_result($result,$i,'skill_name') .'</span></td>
				<td><span class="smaller">'. people_skill_level_box('skill_level_id',db_result($result,$i,'skill_level_id')). '</span></td>
				<td><span class="smaller">'. people_skill_year_box('skill_year_id',db_result($result,$i,'skill_year_id')). '</span></td>
				<td nowrap><span class="smaller"><input type="SUBMIT" name="update_skill_inventory"'
	    .'value="'._("Update").'"> &nbsp;
					<input type="SUBMIT" name="delete_from_skill_inventory"'
	    .'value="'._("Delete").'"></span></td>
			</tr>
			</form>';
	}

    }
  #add a new skill
  $i++; #for row coloring

  print '
	<tr><td colspan="4"><h3>'._("Add A New Skill").'</h3></td></tr>
	<form action="'.$_SERVER['PHP_SELF'].'" method="POST">
	<tr class="'. utils_get_alt_row_color($i) .'">
		<td><span class="smaller">'. people_skill_box('skill_id'). '</span></td>
		<td><span class="smaller">'. people_skill_level_box('skill_level_id'). '</span></td>
		<td><span class="smaller">'. people_skill_year_box('skill_year_id'). '</span></td>
		<td nowrap><span class="smaller"><input type="submit" name="add_to_skill_inventory" value="'
    ._("Add Skill").'"></span></td>
	</tr>
	</form>';

  print '
		</table>';
}

?>
