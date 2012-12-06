<?php
/* Configuration variables */
$max_kids = 10;  // Max number of processes
$min_kids = (int) $max_kids/2; // Minimum number of process (used with Random)
$randomizer = true; // Randomize the number of procs running?
$delay = 1;  // Delay on starting new processes 0 is fine. 
$test_runs = 5000; // How many tests to run before quitting
/* End Configuration variables */

# Setup clock ticks! Needed for signal handlers
declare(ticks = 1);
 
# Make sure this is declared in the global context.
# The array that holds the running children procs, no way to clean up without it. 
$active_kids;

# How many runs have I don so far
$number_of_runs = 0;

# Setup the signal handler.
pcntl_signal(SIGUSR1, "sig_handler");
pcntl_signal(SIGUSR2, "sig_handler");
pcntl_signal(SIGINT, "sig_handler");

# Setup shared memory block
$shm_key = ftok($argv[0],'q');
$shm_id = shm_attach($shm_key,10000,0644);

if($shm_id == false) { 
  print("Unable to access shared memory. FAIL.\n");
  die();
} 

# Loop through for how many tests to complete
while($number_of_runs < $test_runs){
  hulkSmash();
}

# Let any active children die before finishing up.
while(sizeof($active_kids) > 0){ 
  wait_for_kids();    
}

# Print the output of the final timer & be done
print("Executed $test_runs \n");
exit(0);


# This is actually a tetherball term.
function hulkSmash(){
  global $active_kids;
  global $number_of_runs;
  global $max_kids;
  global $min_kids;
  global $randomizer;
  global $shm_id; 
  global $delay;

  # Some flow control, if there are to many children, then STOP
  # forking and making more
  if(is_array($active_kids)){   
  # Randomizer allows for an increase and decrease in the max
  # children, this will allow for some load spikes and sudden bursts
  # as well as some quiet moments
  
    if($randomizer){ 
      $how_many_to_wait_for = (int) mt_rand($min_kids,$max_kids);
    } else { 
      $how_many_to_wait_for = $max_kids;
    }

    # Actually do the waiting now, this can be instant or it can
    # take awhile.
    while(sizeof($active_kids) >= $how_many_to_wait_for){
      wait_for_kids(); 
    }
  }

  # Active kids won't be an array on the first run through
  # so use static values for the first fork.
  if(is_array($active_kids)){  
    $running_procs = sizeof($active_kids); 
    $howmany_more = $how_many_to_wait_for - $running_procs;
  } else { 
    $running_procs = 0;
    $how_many_to_wait_for = 1;
  }

  # Startup all the processes that we need to on this run through 
  # the smasher before waiting again for children. this extra logic
  # is needed to support the random 'max' children and randomizer
  for($i = $running_procs; $i <= $how_many_to_wait_for; $i++){ 
    # Here the fun begins now that we have the green light
    # FORK IT BABY FORK IT
    # WAIT! WAIT! Stop for $delay first, a little poor mans flow control
    sleep($delay);
    $pid = pcntl_fork(); 
    if($pid == -1){ 
      # Maybe there was a bomb, or something... but we couldn't fork
      # a process, if we can't fork, then we must die, afterall forking
      # is the key to life. At least script life. At least this script.
      print("ERROR: A horrible forking problem, couldn't fork.\n");
      die();
    } else if ($pid) { 
      # This is the parent, parents have an easy job... kind of...
      # all you have to do is keep track of the kids!!! Okay, it's harder
      # than it sounds, and it's pretty damned important too.
      $number_of_runs++;
      $active_kids[$pid]=$number_of_runs; 
      # Using shared memory to keep track of the number of running processes
      # this way any child can report on the status of running processes at 
      # any time. 
      shm_put_var($shm_id,0,sizeof($active_kids));	
    } else { 
      # This is the child, so do some work, BUT FOR GODS SAKE, BE SURE TO EXIT!
      $mypid = getmypid();

      /* This is where the cookie cutter code ends. Put the specific stuff
         below here... This enables you to test for what you want to test
         for. */ 

      $running = (shm_get_var($shm_id,0)); 
      shm_put_var($shm_id,0,$running-1);
    
      # The child process does the work, then exits. Exits, that part is key.
      # otherwise... you ARE the bomb, the fork bomb that is.
      exit(0);
    } 
  }
  return(1);
}

# This function waits for a child, to stop fork bombs, or to 
# wait for the system to return
function wait_for_kids(){
  global $active_kids;
   
  $pid = pcntl_waitpid(-1,$status);
  if($pid == -1){ 
    # This shouldn't ever happen...
    die("ERROR: There was a serious forking error.\n\n");
  } else { 
    # A child exited, so remove it from the list of running children.
    unset($active_kids[$pid]);
  }
  return(1);
}

# This function is used to handle incoming signals
function sig_handler($signal){
  global $max_kids; 

  switch($signal){ 
    # Sig USR1 decreases kiddos
    case SIGUSR1: 
      if($max_kids > 1){
        $max_kids--;
      }
      break;
  
    # Sig USR2 increases kiddos
    case SIGUSR2: 
      if($max_kids < 65000){ 
        $max_kids++;      
      }
      break; 
  }
    # CTRL-C while running, remove shared memory.
    # only parent should do this... fixme! 
    case SIGINT:
      global $shm_id;
      global $active_kids;
      print("Got interupt\n");
      print("Cleaning up shared memory...\n");
      shm_remove($shm_id);
      exit(0);
  }
}
