<?php if(!function_exists('startedIndexPhp')) { header("location:../index.php"); exit;}
# streber - a php based project management system
# Copyright (c) 2005 Thomas Mann - thomas@pixtur.de
# Distributed under the terms and conditions of the GPL as stated in docs/license.txt

/**
 * pages relating to efforts
 *
 * @author:         Thomas Mann
 * @uses:           ListBlock
 * @usedby:
 *
 */


require_once(confGet('DIR_STREBER') . 'db/class_task.inc.php');
require_once(confGet('DIR_STREBER') . 'db/class_project.inc.php');
require_once(confGet('DIR_STREBER') . 'db/class_effort.inc.php');
require_once(confGet('DIR_STREBER') . 'render/render_list.inc.php');

#=====================================================================================================
# effortView
#=====================================================================================================
function effortView()
{
	global $PH;
	global $auth;
	require_once(confGet('DIR_STREBER') . 'render/render_wiki.inc.php');

	### get effort ####
    $ids= getPassedIds('effort','efforts_*');

    if(!$ids) {
        $PH->abortWarning(__("Select one or more efforts"));
        return;
	}
	else if(count($ids) > 1) {
		$PH->show('effortViewMultiple');
		exit;
	}
	else if(!$effort= Effort::getEditableById($ids[0])) {
		$PH->abortWarning(__("You do not have enough rights"), ERROR_RIGHTS);
	}

	## get project ##
	if(!$project= Project::getVisibleById($effort->project)) {
        $PH->abortWarning("invalid project-id",ERROR_FATAL);
    }

	## get task #
    $task = $effort->task
          ? Task::getVisibleById($effort->task)
          : NULL;

	### create from handle ###
    $from_handle= $PH->defineFromHandle(array('effort'=>$effort->id));

	## is viewed by user ##
	$effort->nowViewedByUser();

	### set up page and write header ####
    {
        $page= new Page();
    	$page->cur_tab='projects';

        $page->cur_crumb= 'effortView';
        $page->crumbs= build_project_crumbs($project);
        $page->options= build_projView_options($project);

        $type= __('Effort of task','page type');

        if($task) {
             $folder= $task->getFolderLinks() ."<em>&gt;</em>". $task->getLink();
             $page->type= $folder."<em>&gt;</em>". $type;
        }
        else {
             $page->type= $type;
        }

        $page->title=$effort->name;
        $page->title_minor=__("Overview");

        ### page functions ###
        $page->add_function(new PageFunction(array(
            'target'    =>'effortEdit',
            'params'    =>array('effort'=>$effort->id),
            'icon'      =>'edit',
            'tooltip'   =>__('Edit this effort'),
            'name'      =>__('Edit')
        )));
		
		$item = ItemPerson::getAll(array('person'=>$auth->cur_user->id,'item'=>$effort->id));
		if((!$item) || ($item[0]->is_bookmark == 0)){
			$page->add_function(new PageFunction(array(
				'target'    =>'itemsAsBookmark',
				'params'    =>array('effort'=>$effort->id),
				'tooltip'   =>__('Mark this effort as bookmark'),
				'name'      =>__('Bookmark'),
			)));
		}
		else{
			$page->add_function(new PageFunction(array(
				'target'    =>'itemsRemoveBookmark',
				'params'    =>array('effort'=>$effort->id),
				'tooltip'   =>__('Remove this bookmark'),
				'name'      =>__('Remove Bookmark'),
			)));
		} 

    	### render title ###
        echo(new PageHeader);
    }

    echo (new PageContentOpen);

	### details ###
	{
		$block = new PageBlock(array(
			'title'=>__('Details'),
			'id'=>'details',
		));

		$block->render_blockStart();

		echo "<div class=text>";

		if($project) {
			echo "<p><label>" . __('Project','label') . "</label>". $project->getLink(false) ."</p>";
		}

		if($task){
			if($task->parent_task != 0) {
             	$folder= $task->getFolderLinks(false) ."<em> &gt; </em>". $task->getLink(false);
             	echo "<p><label>" . __('Task','label') . "</label>" . $folder . "</p>";
			}
			else {
				echo "<p><label>" . __('Task','label') . "</label>" . $task->getLink(false) . "</p>";
			}
		}
		else {
			echo "<p><label>" . __('Task','label') . "</label>" . __('No task related') . "</p>";
		}

		if(!$person = Person::getById($effort->person)){
			echo "<p><label>" . __('Created by','label') . "</label>" . __('not available') . "</p>";
		}
		else {
			echo "<p><label>" . __('Created by','label') . "</label>" . $person->getLink() . "</p>";
		}

		if($effort){
			$duration = round((strToGMTime($effort->time_end) - strToGMTime($effort->time_start))/60/60,1)." h";
			if($effort->as_duration){
				echo "<p><label>" . __('Created at','label') . "</label>" . renderDateHtml($effort->time_start) . "</p>";
				echo "<p><label>" . __('Duration','label') . "</label>" . asHtml($duration) . "</p>";
			}
			else {
				echo "<p><label>" . __('Time start','label') . "</label>" . renderTimestampHtml($effort->time_start) . "</p>";
				echo "<p><label>" . __('Time end','label') . "</label>" . renderTimestampHtml($effort->time_end) . "</p>";
				echo "<p><label>" . __('Duration','label') . "</label>" . asHtml($duration) . "</p>";
			}
		}

		echo "</div>";

		$block->render_blockEnd();
	}

	### description ###
	{
		$block = new PageBlock(array(
			'title'=>__('Description'),
			'id'=>'description',
		));

		$block->render_blockStart();

		if($effort->description != "") {
			echo "<div class=text>";
			echo wiki2html($effort->description, $effort);

			### update effort if relative links have been converted to ids ###
			global $g_wiki_auto_adjusted;
			if(isset($g_wiki_auto_adjusted) && $g_wiki_auto_adjusted) {
				$effort->description= $g_wiki_auto_adjusted;
				$effort->update(array('description'),false);
			}

			echo "</div>";
		}
		else {
			if($auth->cur_user->user_rights & RIGHT_PROJECT_ASSIGN) {
				echo "<div class=empty>" . $PH->getLink('effortEdit','',array('effort'=>$effort->id)) . "</div>";
			}
			else {
				echo "<div class=text>" . __('No description available') . "</div>";
			}
		}
		$block->render_blockEnd();
	}
	echo (new PageContentClose);
	echo (new PageHtmlEnd);
}

#=====================================================================================================
# effortViewMultiple
#=====================================================================================================
function effortViewMultiple()
{
	global $PH;
	global $auth;
	require_once(confGet('DIR_STREBER') . 'render/render_wiki.inc.php');

	### get effort ####
    $ids= getPassedIds('effort','efforts_*');

	if(!$ids) {
        $PH->abortWarning(__("Select one or more efforts"));
        return;
	}

	$number = 0;
	$sum    = 0;
	$count  = 0;


	foreach($ids as $id) {
	 	if($e= Effort::getEditableById($id)) {
			## array with all efforts ##
			$e_array[] = $e;
			## array with all effort ids (for Effort::getMinMaxTime())##
			$e_ids[] = $e->id;

			## is viewed by user ##
			$e->nowViewedByUser();

			## number of efforts ##
			$number++;
			## sum of all efforts ##
			$sum += round((strToGMTime($e->time_end) - strToGMTime($e->time_start))/60/60,1);

			### check project of first effort
            if(count($e_array) == 1) {
                if(!$project = Project::getVisibleById($e->project)) {
                    $PH->abortWarning('could not get project');
                }
            }
            else {
				$count = 0;
				### check if the efforts are related to the same task ###
                if($e->task != $e_array[0]->task) {
				   $count++;
                }
            }
		}
		else {
			$PH->abortWarning(__("You do not have enough rights"), ERROR_RIGHTS);
		}
	}

	### set up page and write header ####
    {
        $page= new Page();
    	$page->cur_tab='projects';

        $page->cur_crumb= 'effortViewMultiple';
        $page->crumbs= build_project_crumbs($project);
        $page->options= build_projView_options($project);

        $type= __('Multiple Efforts','page type');

		## same tasks ##
        if($count == 0) {
			 $task = $e_array[0]->task
             ? Task::getVisibleById($e_array[0]->task)
          	 : NULL;

			if($task) {
				 $folder= $task->getFolderLinks() ."<em>&gt;</em>". $task->getLink();
				 $page->type= $folder."<em>&gt;</em>". $type;
			}
			else {
				 $page->type= $type;
			}
        }
		## different tasks ##
        else {
             $page->type= $project->getLink() . "<em>&gt;</em>" . $type;
        }

        $page->title=__('Multiple Efforts');
        $page->title_minor=__('Overview');

    	### render title ###
        echo(new PageHeader);
    }

    echo (new PageContentOpen);

	### summary ###

	### title ###
	echo "<div class=text><h3>" . __('summary') . "</h3></div>";
	{
		### content ###
		$block = new PageBlock(array(
				'title'=>__('Information'),
				'id'=>'info',
			    ));

		$block->render_blockStart();

		echo "<div class=text>";

		if($number){
			echo "<p><label>" . __('Number of efforts','label') . "</label>". asHtml($number) ."</p>";
		}

		if($sum) {
			echo "<p><label>" . __('Sum of efforts','label') . "</label>". asHtml($sum) ." h</p>";
		}

		$content['e_ids'] = $e_ids;

		$time = Effort::getMinMaxTime($content);
		if($time) {
			$line = "<p><label>" . __('from','time label') . "</label>" . renderDateHtml($time[0]) . "</p>";
			$line .= "<p><label>" . __('to','time label') . "</label>" . renderDateHtml($time[1]) . "</p>";
			echo $line;
		}
		else {
			echo "<p><label>" . __('Time','label') . "</label>" . __('not available') . "</p>";
		}

		echo "</div>";

		$block->render_blockEnd();
	}

	### start to list efforts ###
	foreach($e_array as $effort)
	{
		### title ###
		echo "<div class=text><h3>" . asHtml($effort->name) . "</h3></div>";

		### details ###
		{
			$block = new PageBlock(array(
				'title'=>__('Details'),
				'id'=>'details' . $effort->id,
			));

			$block->render_blockStart();

			echo "<div class=text>";

			if($project) {
				echo "<p><label>" . __('Project','label') . "</label>". $project->getLink(false) ."</p>";
			}

			$task = $effort->task
             ? Task::getVisibleById($effort->task)
          	 : NULL;

			if($task){
				if($task->parent_task != 0) {
					$folder= $task->getFolderLinks(false) ."<em> &gt; </em>". $task->getLink(false);
					echo "<p><label>" . __('Task','label') . "</label>" . $folder . "</p>";
				}
				else {
					echo "<p><label>" . __('Task','label') . "</label>" . $task->getLink(false) . "</p>";
				}
			}
			else {
				echo "<p><label>" . __('Task','label') . "</label>" . __('No task related') . "</p>";
			}

			if(!$person = Person::getById($effort->person)){
				echo "<p><label>" . __('Created by','label') . "</label>" . __('not available') . "</p>";
			}
			else {
				echo "<p><label>" . __('Created by','label') . "</label>" . $person->getLink() . "</p>";
			}

			if($effort){
				$duration = round((strToGMTime($effort->time_end) - strToGMTime($effort->time_start))/60/60,1)." h";
				if($effort->as_duration){
					echo "<p><label>" . __('Created at','label') . "</label>" . renderDateHtml($effort->time_start) . "</p>";
					echo "<p><label>" . __('Duration','label') . "</label>" . asHtml($duration) . "</p>";
				}
				else {
					echo "<p><label>" . __('Time start','label') . "</label>" . renderTimestampHtml($effort->time_start) . "</p>";
					echo "<p><label>" . __('Time end','label') . "</label>" . renderTimestampHtml($effort->time_end) . "</p>";
					echo "<p><label>" . __('Duration','label') . "</label>" . asHtml($duration) . "</p>";
				}
			}

			echo "</div>";

			$block->render_blockEnd();
		}

		### description ###
		{
			$block = new PageBlock(array(
				'title'=>__('Description'),
				'id'=>'description' . $effort->id,
			));

			$block->render_blockStart();

			if($effort->description != "") {
				echo "<div class=text>";
				echo wiki2html($effort->description, $effort);

				### update effort if relative links have been converted to ids ###
				global $g_wiki_auto_adjusted;
				if(isset($g_wiki_auto_adjusted) && $g_wiki_auto_adjusted) {
					$effort->description= $g_wiki_auto_adjusted;
					$effort->update(array('description'),false);
				}

				echo "</div>";
			}
			else {
				if($auth->cur_user->user_rights & RIGHT_PROJECT_ASSIGN) {
					echo "<div class=empty>" . $PH->getLink('effortEdit','',array('effort'=>$effort->id)) . "</div>";
				}
				else {
					echo "<div class=text>" . __('No description available') . "</div>";
				}
			}
			$block->render_blockEnd();
		}
	}

	echo (new PageContentClose);
	echo (new PageHtmlEnd);
}

#=====================================================================================================
# effortNew
# - requires prj or task or tsk_*
#=====================================================================================================
function effortNew()
{
    global $PH;
    global $auth;

    $name=get('new_name')
        ? get('new_name')
        :__("New Effort");

    ### first try single project-id ###
    $project=NULL;
    if($ids=getPassedIds('prj','projects_*')) {
        if(!$project= Project::getVisibleById($ids[0])) {
            $PH->abortWarning("ERROR: could not get Project");
            return;
        }
    }

    ### try to get task ###
    $task_id=0;
    if($task_ids= getPassedIds('parent_task','tasks_*')) {
        if(count($task_ids) > 1) {
            new FeedbackWarning(__("only expected one task. Used the first one."));
        }
        $task_id= $task_ids[0];
    }


    ### try to get folder ###
    else if($task_ids= getPassedIds('','folders_*')) {
        if(count($task_ids) > 1) {
            new FeedbackWarning( __("only expected one task. Used the first one."));
        }
        $task_id= $task_ids[0];
    }


    if(!$project) {
        if( !$task_id) {
            $PH->abortWarning("Could not get project",ERROR_NOTE);
        }
        if(!$task= Task::getVisibleById($task_id)) {
            $PH->abortWarning("Invalid task id?", ERROR_BUG);
        }
        if(!$project= Project::getVisibleById($task->project)) {
            $PH->abortWarning("Invalid project for task?", ERROR_BUG);
        }
    }

    $now= getGMTString();

    ### last effort selected? ###
    if($effort_ids= getPassedIds('','efforts_*')) {
        $last_effort= Effort::getVisibleById($effort_ids[0]);

        $last=$last_effort->time_end;
        $now=$last_effort->time_end;
    }
    ### guess start-time from last effort / start at 10:00 if new day ###
    else {
        if($last=Effort::getDateCreatedLast()) {
        	$last_day=getdate(strToGMTime($last));
    	}
    	else {
    		$last_day="1980-01-01";
    	}

        $today=getdate(time());
        if($last_day['yday'] != $today['yday']) {
            $last= getGMTString();
        }
    }

    ### log efforts as durations? ###
    $as_duration= 0;
    if($pp= $project->getCurrentProjectPerson()) {
        if($pp->adjust_effort_style == 2) {

            if($last=Effort::getDateCreatedLast()) {
            	$last_day=$last;
        	}
        	else {
        		$last_day="1980-01-01";
        	}
            $str_time=sprintf("%02d:%02d:%02d",
                floor( (time()-strToGMTime($last_day))/60/60%24),
                floor( (time()-strToGMTime($last_day))/60%60),
                floor( (time()-strToGMTime($last_day))%60)
            );

            $now=   gmdate("Y-m-d", time()) ." ". $str_time;
            $last=  gmdate("Y-m-d") ." ". "00:00:00";

            $as_duration=1;
        }
    }


    ### build new object ###
    $newEffort= new Effort(array(
        'id'        =>0,
        'name'      =>$name,
        'project'   =>$project->id,
        'time_start'=>$last,
        'time_end'  =>$now,
        'task'      =>$task_id,
        'as_duration'=>$as_duration,
        'person'    =>$auth->cur_user->id,
        )
    );
    $PH->show('effortEdit',array('effort'=>$newEffort->id),$newEffort);
}


#=====================================================================================================
# effort edit
#=====================================================================================================
function effortEdit($effort=NULL) {
    global $PH;
	global $g_effort_status_names;

    if(!$effort) {
        $id= getOnePassedId('effort','efforts_*');   # WARNS if multiple; ABORTS if no id found
        if(!$effort= Effort::getEditableById($id)) {
            $PH->abortWarning("ERROR: could not get Effort");
            return;
        }
    }

    if(!$project=Project::getVisibleById($effort->project)) {
            $PH->abortWarning("ERROR: could not get Project",ERROR_BUG);
    }

    $task= Task::getVisibleById($effort->task);

    ### set up page and write header ####
    {
        $page= new Page(array('use_jscalendar'=>true, 'autofocus_field'=>'effort_name'));
    	$page->cur_tab='projects';
        $page->type=__('Edit Effort','page type');
        $page->title=$effort->name;

        if($task) {
            $page->crumbs= build_task_crumbs($task);
        }
        else {
            $page->crumbs= build_project_crumbs($project);
        }
        $page->crumbs[]= new NaviCrumb(array(
            'target_id' => 'effortEdit',
        ));

        if($effort->id) {
            $page->title=__('Edit Effort','page title');
        }
        else {
            $page->title=__('New Effort','page title');
        }

        $page->title_minor= sprintf(__('On project %s','page title add on'),$project->name);


        echo(new PageHeader);
    }
    echo (new PageContentOpen);

    ### write form #####
    {
        $block=new PageBlock(array(
            'id'    =>'edit',
            'reduced_header' => true,
        ));
        $block->render_blockStart();


        require_once(confGet('DIR_STREBER') . 'render/render_form.inc.php');

        $form=new PageForm();
        $form->button_cancel=true;

        ### automatically write fields ###
#        foreach($effort->fields as $field) {
#            $form->add($field->getFormElement(&$effort));
#        }

        $form->add($effort->fields['name']->getFormElement(&$effort));

        if($effort->as_duration) {
            /**
            * NOTE:
            * - Durations are stored with two datetimes in GMT. The first is
            *   starting at 00:00:00 GMT the second defined the distant also in GMT.
            *   Since the edit form would try to convert the time_end into client time
            *   we overwrite it here. This might be called a hack.
            */
            $effort->time_end=clientTimeStrToGMTString($effort->time_end);

            $tmp_edit= $effort->fields['time_end']->getFormElement(&$effort);
            $tmp_edit->title=__("Date / Duration","Field label when booking time-effort as duration");
            $form->add($tmp_edit);
        }
        else {
            $form->add($effort->fields['time_start']->getFormElement(&$effort));
            $form->add($effort->fields['time_end']->getFormElement(&$effort));
        }
        $form->add($effort->fields['description']->getFormElement(&$effort));
		$form->add(new Form_Dropdown("effort_status", __('Status'), array_flip($g_effort_status_names), $effort->status));

        ### get meta-tasks / folders ###
        #$folders= $project->getFolders();
        $folders= Task::getAll(array(
            'sort_hierarchical'=>true,
            'parent_task'=> 0,
            'show_folders'=>true,
            'folders_only'=>false,
            'status_min'=> STATUS_UPCOMING,
            'status_max'=> STATUS_CLOSED,
            'project'=> $project->id,

        ));
        if($folders) {
            $folder_list= array("undefined"=>"0");

            if($effort->task) {
                if($task= Task::getVisibleById($effort->task)) {
                    ### add, if normal task (folders will added below) ###
                    if(! $task->category == TCATEGORY_FOLDER) {
                        $folder_list[$task->name]= $task->id;
                    }
                }
            }

            foreach($folders as $f) {
                $str= '';
                foreach($f->getFolder() as $pf) {
                    $str.=$pf->getShort(). " > ";
                }
                $str.=$f->name;

                $folder_list[$str]= $f->id;

            }
            $form->add(new Form_Dropdown('effort_task',  __("For task"),$folder_list, $effort->task));

        }

        ### public-level ###
        if(($pub_levels= $effort->getValidUserSetPublevel())
            && count($pub_levels)>1) {
            $form->add(new Form_Dropdown('effort_pub_level',  __("Publish to"),$pub_levels,$effort->pub_level));
        }


        echo ($form);
        $block->render_blockEnd();

        $PH->go_submit='effortEditSubmit';
        echo "<input type=hidden name='effort_as_duration' value='$effort->as_duration'>";
        echo "<input type=hidden name='effort' value='$effort->id'>";
        echo "<input type=hidden name='effort_project' value='$effort->project'>";
        echo "<input type=hidden name='effort_person' value='$effort->person'>";
    }
    echo (new PageContentClose);
	echo (new PageHtmlEnd);
}

#=====================================================================================================
# effortEditSubmit
#=====================================================================================================
function effortEditSubmit()
{
    global $PH;


    ### get effort ####
    $id= getOnePassedId('effort');

    if($id == 0) {
        $effort= new Effort(array('id'=>0));
    }
    else {
        $effort= Effort::getEditableById($id);
        if(!$effort) {
            $PH->abortWarning(__("Could not get effort"));
            return;
        }
    }

    ### cancel ###
    if(get('form_do_cancel')) {
        if(!$PH->showFromPage()) {
            $PH->show('projView',array('prj'=>$effort->project));
        }
        exit;
    }

    ### get project ###
    $effort->project=get('effort_project');
    if(!$project = Project::getVisibleById($effort->project)) {
        $PH->abortWarning(__("Could not get project of effort"));
    }

    ### get person ###
    if($effort->person=get('effort_person')) {
        if(!$person = Person::getVisibleById($effort->person)) {
            $PH->abortWarning(__("Could not get person of effort"));
        }
    }

    # retrieve all possible values from post-data
    # NOTE:
    # - this could be an security-issue.
    # - TODO: as some kind of form-edit-behaviour to field-definition
    foreach($effort->fields as $f) {
        $name=$f->name;
        $f->parseForm(&$effort);
    }

    ### times as duration ###
    if($as_duration= get('effort_as_duration')) {
        $effort->as_duration= $as_duration;

        ### make sure day of time_end stays the same if date changes... ###
        if(($time_start= $effort->time_start) && ($time_end= $effort->time_end)) {

            $effort->time_end=
                gmdate("Y-m-d", strToClientTime($time_end) )
                ." ".
                gmdate("H:i:s", strToClientTime($time_end) );

            $effort->time_start=
                gmdate("Y-m-d", strToClientTime($time_end) )
                ." ".
                gmdate("00:00:00", strToClientTime($time_end) );

        }
        else {
            trigger_error("Getting time_start and time_end failed",E_USER_WARNING);
        }
    }


    ### pub level ###
    if($pub_level=get('effort_pub_level')) {

        ### not a new effort ###
        if($effort->id) {
             if($pub_level > $effort->getValidUserSetPublevel() ) {
                 $PH->abortWarning('invalid data',ERROR_RIGHTS);
             }
        }
        #else {
        #    #@@@ check for person create rights
        #}
        $effort->pub_level = $pub_level;
    }
	
	## effort status ##
	if($effort_status = get('effort_status')){
		$effort->status = $effort_status;
	}

    ### link to task ###
    if($task_id = get('effort_task')) {
        if($task= Task::getVisibleById($task_id)) {
            $effort->task = $task->id;
        }
    }


    ### go back to from if validation fails ###
    $failure= false;
    if(!$effort->name) {
        $failure= true;
        new FeedbackWarning(__("Name required"));
    }
    if(strToGMTime($effort->time_end) - strToGMTime($effort->time_start) < 0) {
        $failure= true;
        new FeedbackWarning(__("Cannot start before end."));
    }
    if($failure) {
        $PH->show('effortEdit',NULL,$effort);
        exit;
    }


    ### write to db ###
    if($effort->id == 0) {
        $effort->insert();
    }
    else {
        $effort->update();
    }

    ### display taskView ####
    if(!$PH->showFromPage()) {
        $PH->show('projView',array('prj'=>$effort->project));
    }
}


#=====================================================================================================
# effortsDelete
#=====================================================================================================
function effortsDelete()
{
    global $PH;

    ### get effort ####
    $ids= getPassedIds('effort','efforts_*');

    if(!$ids) {
        $PH->abortWarning(__("Select some efforts to delete"));
        return;
    }

    $counter=0;
    $errors=0;
    foreach($ids as $id) {
        $e= Effort::getEditableById($id);
        if(!$e) {
            $PH->abortWarning("Invalid effort-id!");
        }
        if($e->delete()) {
            $counter++;
        }
        else {
            $errors++;
        }
    }
    if($errors) {
        new FeedbackWarning(sprintf(__("Failed to delete %s efforts"), $errors));
    }
    else {
        new FeedbackMessage(sprintf(__("Moved %s efforts to trash"),$counter));
    }

    if(!$PH->showFromPage()) {
        $PH->show('home');
    }
}

?>
