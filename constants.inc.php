<?php

# status
define( 'NEW_',				10 );   # NEW seems to be a reserved keyword
define( 'FEEDBACK',			20 );
define( 'ACKNOWLEDGED',		30 );
define( 'CONFIRMED',		40 );
define( 'ASSIGNED',			50 );
define( 'RESOLVED',			80 );
define( 'CLOSED',			90 );

# resolution
define( 'OPEN',					10 );
define( 'FIXED',				20 );
define( 'REOPENED',				30 );
define( 'UNABLE_TO_DUPLICATE',	40 );
define( 'NOT_FIXABLE',			50 );
define( 'DUPLICATE',			60 );
define( 'NOT_A_BUG',			70 );
define( 'SUSPENDED',			80 );
define( 'WONT_FIX',				90 );

# priority
define( 'NONE',			10 );
define( 'LOW',			20 );
define( 'NORMAL',		30 );
define( 'HIGH',			40 );
define( 'URGENT',		50 );
define( 'IMMEDIATE',	60 );

# severity
define( 'FEATURE',	10 );
define( 'TRIVIAL',	20 );
define( 'TEXT',		30 );
define( 'TWEAK',	40 );
define( 'MINOR',	50 );
define( 'MAJOR',	60 );
define( 'CRASH',	70 );
define( 'BLOCK',	80 );

# reproducibility
define( 'REPRODUCIBILITY_ALWAYS',		10 );
define( 'REPRODUCIBILITY_SOMETIMES',	30 );
define( 'REPRODUCIBILITY_RANDOM',		50 );
define( 'REPRODUCIBILITY_HAVENOTTRIED', 70 );
define( 'REPRODUCIBILITY_UNABLETODUPLICATE', 90 );
define( 'REPRODUCIBILITY_NOTAPPLICABLE', 100 );

# project view_state
define( 'VS_PUBLIC',	10 );
define( 'VS_PRIVATE',	50 );

# history constants
define( 'NORMAL_TYPE',					0 );
define( 'NEW_BUG',						1 );
define( 'BUGNOTE_ADDED',				2 );
define( 'BUGNOTE_UPDATED',				3 );
define( 'BUGNOTE_DELETED',				4 );
define( 'DESCRIPTION_UPDATED',			6 );
define( 'ADDITIONAL_INFO_UPDATED',		7 );
define( 'STEP_TO_REPRODUCE_UPDATED',	8 );
define( 'FILE_ADDED',					9 );
define( 'FILE_DELETED',					10 );
define( 'BUGNOTE_STATE_CHANGED',		11 );
define( 'BUG_MONITOR',					12 );
define( 'BUG_UNMONITOR',				13 );
define( 'BUG_DELETED',					14 );
define( 'BUG_ADD_SPONSORSHIP',				15 );
define( 'BUG_UPDATE_SPONSORSHIP',			16 );
define( 'BUG_DELETE_SPONSORSHIP',			17 );
define( 'BUG_ADD_RELATIONSHIP', 		18 );
define( 'BUG_DEL_RELATIONSHIP', 		19 );
define( 'BUG_CLONED_TO', 				20 );
define( 'BUG_CREATED_FROM', 			21 );
define( 'CHECKIN',				22 );
define( 'BUG_REPLACE_RELATIONSHIP', 		23 );
define( 'BUG_PAID_SPONSORSHIP', 		24 );
define( 'TAG_ATTACHED', 				25 );
define( 'TAG_DETACHED', 				26 );
define( 'TAG_RENAMED', 					27 );

# bug relationship constants
define( 'BUG_DUPLICATE',	0 );
define( 'BUG_RELATED',		1 );
define( 'BUG_DEPENDANT',	2 );
define( 'BUG_BLOCKS', 3 );
define( 'BUG_HAS_DUPLICATE', 4 );

# bugnote types
define( 'BUGNOTE', 0 );
define( 'REMINDER', 1 );
define( 'TIME_TRACKING', 2 );
