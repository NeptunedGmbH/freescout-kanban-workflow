<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Skip If Already On Board
    |--------------------------------------------------------------------------
    |
    | When enabled, the workflow action succeeds without creating a duplicate
    | card if the conversation is already linked to the selected board.
    |
    */
    'skip_if_already_on_board' => env('KANBAN_WORKFLOW_SKIP_IF_ALREADY_ON_BOARD', true),

];
