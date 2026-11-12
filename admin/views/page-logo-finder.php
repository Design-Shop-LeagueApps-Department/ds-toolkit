<?php
/**
 * Logo Finder tab content.
 * Rendered inside the shared wrap + header in DS_Toolkit_Admin::render_page().
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="dst-lf-toolbar">
    <input type="text" id="dst-lf-search" placeholder="Search by name, mascot, or acronym (e.g. OSU, AU, UNC)…" autocomplete="off">
    <span id="dst-lf-count"></span>
    <button type="button" class="button" id="dst-lf-select-all">Select All</button>
    <button type="button" class="button" id="dst-lf-clear">Clear</button>
</div>

<div id="dst-lf-grid"></div>

<div id="dst-lf-load-more-wrap">
    <button type="button" class="button dst-is-hidden" id="dst-lf-load-more">Load More</button>
</div>

<!-- Floating import bar — animates up when logos are selected -->
<div id="dst-lf-import-bar">
    <span id="dst-lf-selected-label"></span>
    <button type="button" class="button button-primary" id="dst-lf-import">Import Selected</button>
</div>

<div id="dst-lf-results"></div>
