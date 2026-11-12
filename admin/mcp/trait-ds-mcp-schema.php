<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MCP tools: the description and arguments of every tool, as tools/list sends them.
 *
 * One part of DS_MCP_Server, which was a single 2,800-line class until the 2026-11 audit. The methods
 * were moved here as they were: a trait, so they are still methods of the server (same visibility, same
 * $this). Which switch turns a tool on, which capability it needs and whether it is logged is in
 * DS_MCP_Tools; this file is only what each tool does.
 */
trait DS_MCP_Tool_Schema {

    // -------------------------------------------------------------------------
    // Tool Schema
    // -------------------------------------------------------------------------

    /** name => description, for the MCP tab's reference (the same text tools/list sends). */
    public function tool_descriptions() {
        $out = array();
        foreach ( $this->get_tools_schema() as $tool ) {
            $out[ $tool['name'] ] = isset( $tool['description'] ) ? (string) $tool['description'] : '';
        }
        return $out;
    }

    private function get_tools_schema() {
        return array(
            // Posts & Pages
            array(
                'name'        => 'list_posts',
                'description' => 'List WordPress posts, pages, or custom post types. Returns ID, title, status, type, URL, thumbnail_id, and dates.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_type'   => array( 'type' => 'string',  'description' => 'Post type slug: post, page, events, athletes, staff, teams, or any CPT. Default: post' ),
                        'per_page'    => array( 'type' => 'integer', 'description' => 'Number of results (max 100). Default: 20' ),
                        'search'      => array( 'type' => 'string',  'description' => 'Keyword search' ),
                        'status'      => array( 'type' => 'string',  'description' => 'Filter by status: publish, draft, private, any. Default: any' ),
                        'post_parent' => array( 'type' => 'integer', 'description' => 'Filter by parent post ID. Use 0 for top-level posts.' ),
                        'orderby'     => array( 'type' => 'string',  'description' => 'Sort field: date, title, menu_order, ID, modified. Default: date' ),
                        'order'       => array( 'type' => 'string',  'description' => 'Sort direction: ASC or DESC. Default: DESC' ),
                    ),
                ),
            ),
            array(
                'name'        => 'get_post',
                'description' => 'Get the full content of a post, page, or CPT entry by ID. Returns thumbnail_id for the featured image.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'id' ),
                    'properties' => array(
                        'id' => array( 'type' => 'integer', 'description' => 'WordPress post ID' ),
                    ),
                ),
            ),
            array(
                'name'        => 'create_post',
                'description' => 'Create a new post, page, or CPT entry. Optionally assign taxonomy terms in the same call.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'title' ),
                    'properties' => array(
                        'title'          => array( 'type' => 'string',  'description' => 'Title' ),
                        'content'        => array( 'type' => 'string',  'description' => 'Content (HTML allowed)' ),
                        'excerpt'        => array( 'type' => 'string',  'description' => 'Excerpt' ),
                        'status'         => array( 'type' => 'string',  'description' => 'draft, publish, private. Default: draft' ),
                        'post_type'      => array( 'type' => 'string',  'description' => 'Post type slug. Default: post' ),
                        'post_parent'    => array( 'type' => 'integer', 'description' => 'Parent post ID (for page hierarchy)' ),
                        'slug'           => array( 'type' => 'string',  'description' => 'URL slug (post_name). Auto-generated from title if omitted.' ),
                        'menu_order'     => array( 'type' => 'integer', 'description' => 'Menu/page order. Default: 0' ),
                        'page_template'  => array( 'type' => 'string',  'description' => 'Page template filename, e.g. "template-full-width.php"' ),
                        'post_author'    => array( 'type' => 'integer', 'description' => 'Author user ID. Defaults to current user.' ),
                        'comment_status' => array( 'type' => 'string',  'description' => 'open or closed. Default: closed' ),
                        'thumbnail_id'   => array( 'type' => 'integer', 'description' => 'Media Library attachment ID to set as the featured image. Use 0 to remove.' ),
                        'terms'          => array( 'type' => 'object',  'description' => 'Taxonomy terms to assign. Keys are taxonomy slugs, values are arrays of term IDs or slugs. Example: {"category":[1,3],"event_category":["basketball"]}' ),
                    ),
                ),
            ),
            array(
                'name'        => 'update_post',
                'description' => 'Update an existing post, page, or CPT entry. Only provided fields are changed. Optionally assign taxonomy terms.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'id' ),
                    'properties' => array(
                        'id'             => array( 'type' => 'integer', 'description' => 'Post ID' ),
                        'title'          => array( 'type' => 'string',  'description' => 'New title' ),
                        'content'        => array( 'type' => 'string',  'description' => 'New content (HTML allowed)' ),
                        'excerpt'        => array( 'type' => 'string',  'description' => 'New excerpt' ),
                        'status'         => array( 'type' => 'string',  'description' => 'New status: draft, publish, private' ),
                        'post_parent'    => array( 'type' => 'integer', 'description' => 'Parent post ID. Set to 0 to remove parent.' ),
                        'slug'           => array( 'type' => 'string',  'description' => 'New URL slug (post_name)' ),
                        'menu_order'     => array( 'type' => 'integer', 'description' => 'Menu/page order' ),
                        'page_template'  => array( 'type' => 'string',  'description' => 'Page template filename' ),
                        'post_author'    => array( 'type' => 'integer', 'description' => 'New author user ID' ),
                        'comment_status' => array( 'type' => 'string',  'description' => 'open or closed' ),
                        'thumbnail_id'   => array( 'type' => 'integer', 'description' => 'Media Library attachment ID to set as the featured image. Use 0 to remove.' ),
                        'terms'          => array( 'type' => 'object',  'description' => 'Taxonomy terms to assign. Keys are taxonomy slugs, values are arrays of term IDs or slugs.' ),
                    ),
                ),
            ),
            array(
                'name'        => 'delete_post',
                'description' => 'Delete (or trash) a post, page, or CPT entry.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'id' ),
                    'properties' => array(
                        'id'    => array( 'type' => 'integer', 'description' => 'Post ID' ),
                        'force' => array( 'type' => 'boolean', 'description' => 'true = permanent delete, false = trash (default)' ),
                    ),
                ),
            ),
            array(
                'name'        => 'bulk_create_posts',
                'description' => 'Create multiple posts, pages, or CPT entries in one call. Ideal for CSV imports. Returns per-item results with IDs and any errors.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'posts' ),
                    'properties' => array(
                        'posts' => array(
                            'type'        => 'array',
                            'description' => 'Array of post objects to create. Each supports the same fields as create_post: title, content, excerpt, status, post_type, post_parent, slug, menu_order, page_template, post_author, comment_status, thumbnail_id, terms.',
                            'items'       => array( 'type' => 'object' ),
                        ),
                    ),
                ),
            ),
            array(
                'name'        => 'bulk_update_posts',
                'description' => 'Update multiple posts in one call. Each item must include an id. Only provided fields are changed. Returns per-item results.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'posts' ),
                    'properties' => array(
                        'posts' => array(
                            'type'        => 'array',
                            'description' => 'Array of post objects to update. Each must have id plus any fields to change: title, content, excerpt, status, post_parent, slug, menu_order, page_template, post_author, comment_status, thumbnail_id, terms.',
                            'items'       => array( 'type' => 'object' ),
                        ),
                    ),
                ),
            ),
            // CPT Discovery
            array(
                'name'        => 'list_post_types',
                'description' => 'List all registered public post types on this WordPress site — useful to discover available CPTs like events, athletes, staff, teams.',
                'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            ),
            // Taxonomies
            array(
                'name'        => 'list_taxonomies',
                'description' => 'List all registered public taxonomies and which post types they belong to.',
                'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            ),
            array(
                'name'        => 'list_terms',
                'description' => 'List terms in a taxonomy (e.g. athlete-category, staff-category, team-category).',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'taxonomy' ),
                    'properties' => array(
                        'taxonomy'   => array( 'type' => 'string',  'description' => 'Taxonomy slug' ),
                        'search'     => array( 'type' => 'string',  'description' => 'Search keyword' ),
                        'per_page'   => array( 'type' => 'integer', 'description' => 'Max results (default 100, max 200)' ),
                        'hide_empty' => array( 'type' => 'boolean', 'description' => 'Exclude terms with no posts. Default: false' ),
                    ),
                ),
            ),
            array(
                'name'        => 'get_term',
                'description' => 'Get a single taxonomy term by ID or slug.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'taxonomy' ),
                    'properties' => array(
                        'taxonomy' => array( 'type' => 'string',  'description' => 'Taxonomy slug' ),
                        'id'       => array( 'type' => 'integer', 'description' => 'Term ID (use id or slug)' ),
                        'slug'     => array( 'type' => 'string',  'description' => 'Term slug (use id or slug)' ),
                    ),
                ),
            ),
            array(
                'name'        => 'create_term',
                'description' => 'Create a new term in a taxonomy.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'taxonomy', 'name' ),
                    'properties' => array(
                        'taxonomy'    => array( 'type' => 'string', 'description' => 'Taxonomy slug' ),
                        'name'        => array( 'type' => 'string', 'description' => 'Term name' ),
                        'slug'        => array( 'type' => 'string', 'description' => 'Term slug (optional, auto-generated from name)' ),
                        'description' => array( 'type' => 'string', 'description' => 'Term description' ),
                        'parent'      => array( 'type' => 'integer','description' => 'Parent term ID (for hierarchical taxonomies)' ),
                    ),
                ),
            ),
            array(
                'name'        => 'update_term',
                'description' => 'Update an existing taxonomy term.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'id', 'taxonomy' ),
                    'properties' => array(
                        'id'          => array( 'type' => 'integer', 'description' => 'Term ID' ),
                        'taxonomy'    => array( 'type' => 'string',  'description' => 'Taxonomy slug' ),
                        'name'        => array( 'type' => 'string',  'description' => 'New name' ),
                        'slug'        => array( 'type' => 'string',  'description' => 'New slug' ),
                        'description' => array( 'type' => 'string',  'description' => 'New description' ),
                    ),
                ),
            ),
            array(
                'name'        => 'set_post_terms',
                'description' => 'Assign or replace taxonomy terms on a post. Use append=true to add without removing existing terms.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'id', 'taxonomy', 'terms' ),
                    'properties' => array(
                        'id'       => array( 'type' => 'integer', 'description' => 'Post ID' ),
                        'taxonomy' => array( 'type' => 'string',  'description' => 'Taxonomy slug (e.g. category, event_category, athlete-category)' ),
                        'terms'    => array( 'type' => 'array',   'description' => 'Array of term IDs (integers) or slugs (strings)' ),
                        'append'   => array( 'type' => 'boolean', 'description' => 'true = add to existing terms, false = replace all (default: false)' ),
                    ),
                ),
            ),
            array(
                'name'        => 'delete_term',
                'description' => 'Delete a taxonomy term.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'id', 'taxonomy' ),
                    'properties' => array(
                        'id'       => array( 'type' => 'integer', 'description' => 'Term ID' ),
                        'taxonomy' => array( 'type' => 'string',  'description' => 'Taxonomy slug' ),
                    ),
                ),
            ),
            // ACF / Custom Fields
            array(
                'name'        => 'get_post_fields',
                'description' => 'Get all ACF (or custom meta) fields for a post, page, or CPT entry. Uses get_fields() if ACF is active, otherwise returns post meta.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'id' ),
                    'properties' => array(
                        'id' => array( 'type' => 'integer', 'description' => 'Post ID' ),
                    ),
                ),
            ),
            array(
                'name'        => 'update_post_fields',
                'description' => 'Update ACF (or custom meta) fields on a post. Pass a fields object with field_name → value pairs.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'id', 'fields' ),
                    'properties' => array(
                        'id'     => array( 'type' => 'integer', 'description' => 'Post ID' ),
                        'fields' => array( 'type' => 'object',  'description' => 'Object of field_name → value pairs to update' ),
                    ),
                ),
            ),
            // Partner Settings
            array(
                'name'        => 'get_partner_settings',
                'description' => 'Read all ACF Partner Settings (logo, email, phone, address, and social links). Requires ACF.',
                'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            ),
            array(
                'name'        => 'update_partner_settings',
                'description' => 'Update one or more ACF Partner Settings fields. Accepts any combination of: partner_logo (attachment ID), partner_email, partner_phone, partner_address, partner_fb, partner_instagram, partner_x, partner_youtube, partner_linkedin, partner_tiktok, partner_leagueapps. Requires manage_options.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'fields' ),
                    'properties' => array(
                        'fields' => array(
                            'type'        => 'object',
                            'description' => 'Object of field_name → value pairs. Social/URL fields expect full URLs. partner_logo expects a Media Library attachment ID.',
                            'properties'  => array(
                                'partner_logo'       => array( 'type' => 'integer', 'description' => 'Media Library attachment ID for the partner logo' ),
                                'partner_email'      => array( 'type' => 'string',  'description' => 'Partner contact email address' ),
                                'partner_phone'      => array( 'type' => 'string',  'description' => 'Partner phone number' ),
                                'partner_address'    => array( 'type' => 'string',  'description' => 'Partner mailing address' ),
                                'partner_fb'         => array( 'type' => 'string',  'description' => 'Facebook page URL' ),
                                'partner_instagram'  => array( 'type' => 'string',  'description' => 'Instagram profile URL' ),
                                'partner_x'          => array( 'type' => 'string',  'description' => 'X (Twitter) profile URL' ),
                                'partner_youtube'    => array( 'type' => 'string',  'description' => 'YouTube channel URL' ),
                                'partner_linkedin'   => array( 'type' => 'string',  'description' => 'LinkedIn page URL' ),
                                'partner_tiktok'     => array( 'type' => 'string',  'description' => 'TikTok profile URL' ),
                                'partner_leagueapps' => array( 'type' => 'string',  'description' => 'LeagueApps site URL' ),
                            ),
                        ),
                    ),
                ),
            ),
            // Toolkit Settings
            array(
                'name'        => 'get_toolkit_settings',
                'description' => 'Read current DS Toolkit settings — feature toggles, column counts, template IDs, etc. Requires manage_options.',
                'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            ),
            array(
                'name'        => 'update_toolkit_settings',
                'description' => 'Update DS Toolkit settings by passing a key-value settings object. Requires manage_options.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'settings' ),
                    'properties' => array(
                        'settings' => array(
                            'type'        => 'object',
                            'description' => 'Key-value map of settings to update.',
                        ),
                    ),
                ),
            ),
            // ACF Schema — Post Types
            array(
                'name'        => 'acf_list_post_types',
                'description' => 'List all custom post types registered via ACF Pro. Returns key, slug, labels, and settings. Requires manage_options + @leagueapps.com.',
                'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            ),
            array(
                'name'        => 'acf_create_post_type',
                'description' => 'Create a new custom post type via ACF Pro. Requires manage_options + @leagueapps.com.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'post_type', 'label', 'singular_label' ),
                    'properties' => array(
                        'post_type'      => array( 'type' => 'string',  'description' => 'Post type slug — lowercase, max 20 chars, alphanumeric/underscores/dashes only (e.g. "athletes")' ),
                        'label'          => array( 'type' => 'string',  'description' => 'Plural label (e.g. "Athletes")' ),
                        'singular_label' => array( 'type' => 'string',  'description' => 'Singular label (e.g. "Athlete")' ),
                        'description'    => array( 'type' => 'string',  'description' => 'Optional description' ),
                        'public'         => array( 'type' => 'boolean', 'description' => 'Whether the post type is publicly accessible. Default: true' ),
                        'hierarchical'   => array( 'type' => 'boolean', 'description' => 'Whether the post type is hierarchical like pages. Default: false' ),
                        'supports'       => array( 'type' => 'array',   'description' => 'Features supported: title, editor, thumbnail, excerpt, custom-fields, revisions, etc. Default: [title, editor, thumbnail, custom-fields]' ),
                        'taxonomies'     => array( 'type' => 'array',   'description' => 'Array of taxonomy slugs to associate with this post type' ),
                    ),
                ),
            ),
            array(
                'name'        => 'acf_update_post_type',
                'description' => 'Update an existing ACF Pro post type by its ACF key. Only provided fields are changed. Requires manage_options + @leagueapps.com.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'key' ),
                    'properties' => array(
                        'key'            => array( 'type' => 'string',  'description' => 'ACF post type key — get from acf_list_post_types' ),
                        'label'          => array( 'type' => 'string',  'description' => 'New plural label' ),
                        'singular_label' => array( 'type' => 'string',  'description' => 'New singular label' ),
                        'description'    => array( 'type' => 'string',  'description' => 'New description' ),
                        'public'         => array( 'type' => 'boolean', 'description' => 'Public visibility' ),
                        'hierarchical'   => array( 'type' => 'boolean', 'description' => 'Hierarchical structure' ),
                        'supports'       => array( 'type' => 'array',   'description' => 'Supported features array' ),
                        'taxonomies'     => array( 'type' => 'array',   'description' => 'Associated taxonomy slugs' ),
                    ),
                ),
            ),
            array(
                'name'        => 'acf_delete_post_type',
                'description' => 'Permanently delete an ACF Pro post type by its ACF key. WARNING: This is irreversible. Requires manage_options + @leagueapps.com.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'key' ),
                    'properties' => array(
                        'key' => array( 'type' => 'string', 'description' => 'ACF post type key — get from acf_list_post_types' ),
                    ),
                ),
            ),
            // ACF Schema — Taxonomies
            array(
                'name'        => 'acf_list_taxonomies',
                'description' => 'List all taxonomies registered via ACF Pro. Returns key, slug, labels, and associated post types. Requires manage_options + @leagueapps.com.',
                'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            ),
            array(
                'name'        => 'acf_create_taxonomy',
                'description' => 'Create a new taxonomy via ACF Pro. Requires manage_options + @leagueapps.com.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'taxonomy', 'label', 'singular_label' ),
                    'properties' => array(
                        'taxonomy'       => array( 'type' => 'string',  'description' => 'Taxonomy slug — lowercase, max 32 chars, alphanumeric/underscores/dashes (e.g. "athlete-category")' ),
                        'label'          => array( 'type' => 'string',  'description' => 'Plural label (e.g. "Athlete Categories")' ),
                        'singular_label' => array( 'type' => 'string',  'description' => 'Singular label (e.g. "Athlete Category")' ),
                        'description'    => array( 'type' => 'string',  'description' => 'Optional description' ),
                        'public'         => array( 'type' => 'boolean', 'description' => 'Whether publicly accessible. Default: true' ),
                        'hierarchical'   => array( 'type' => 'boolean', 'description' => 'Whether hierarchical like categories (true) or flat like tags (false). Default: false' ),
                        'object_type'    => array( 'type' => 'array',   'description' => 'Post type slugs to associate this taxonomy with (e.g. ["athletes", "teams"])' ),
                    ),
                ),
            ),
            array(
                'name'        => 'acf_update_taxonomy',
                'description' => 'Update an existing ACF Pro taxonomy by its ACF key. Only provided fields are changed. Requires manage_options + @leagueapps.com.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'key' ),
                    'properties' => array(
                        'key'            => array( 'type' => 'string',  'description' => 'ACF taxonomy key — get from acf_list_taxonomies' ),
                        'label'          => array( 'type' => 'string',  'description' => 'New plural label' ),
                        'singular_label' => array( 'type' => 'string',  'description' => 'New singular label' ),
                        'description'    => array( 'type' => 'string',  'description' => 'New description' ),
                        'public'         => array( 'type' => 'boolean', 'description' => 'Public visibility' ),
                        'hierarchical'   => array( 'type' => 'boolean', 'description' => 'Hierarchical structure' ),
                        'object_type'    => array( 'type' => 'array',   'description' => 'Associated post type slugs' ),
                    ),
                ),
            ),
            array(
                'name'        => 'acf_delete_taxonomy',
                'description' => 'Permanently delete an ACF Pro taxonomy by its ACF key. WARNING: This is irreversible. Requires manage_options + @leagueapps.com.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'key' ),
                    'properties' => array(
                        'key' => array( 'type' => 'string', 'description' => 'ACF taxonomy key — get from acf_list_taxonomies' ),
                    ),
                ),
            ),
            // ACF Schema — Field Groups
            array(
                'name'        => 'acf_list_field_groups',
                'description' => 'List all ACF field groups. Returns key, title, active status, and location rules. Requires manage_options + @leagueapps.com.',
                'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            ),
            array(
                'name'        => 'acf_get_field_group',
                'description' => 'Get a single ACF field group by key, including its fields (key, label, name, type, required). Requires manage_options + @leagueapps.com.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'key' ),
                    'properties' => array(
                        'key' => array( 'type' => 'string', 'description' => 'ACF field group key — get from acf_list_field_groups' ),
                    ),
                ),
            ),
            array(
                'name'        => 'acf_create_field_group',
                'description' => 'Create a new ACF field group, optionally with fields. Requires manage_options + @leagueapps.com.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'title' ),
                    'properties' => array(
                        'title'           => array( 'type' => 'string', 'description' => 'Field group title' ),
                        'location'        => array( 'type' => 'array',  'description' => 'ACF location rules array. Defaults to show on all posts if omitted.' ),
                        'position'        => array( 'type' => 'string', 'description' => 'Meta box position: normal, side, acf_after_title. Default: normal' ),
                        'label_placement' => array( 'type' => 'string', 'description' => 'Label placement: top or left. Default: top' ),
                        'active'          => array( 'type' => 'boolean', 'description' => 'Whether the group is active. Default: true' ),
                        'fields'          => array(
                            'type'        => 'array',
                            'description' => 'Optional fields to create. Each field: {label, name, type, required, instructions, default_value}',
                            'items'       => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'label'         => array( 'type' => 'string' ),
                                    'name'          => array( 'type' => 'string' ),
                                    'type'          => array( 'type' => 'string', 'description' => 'ACF field type: text, textarea, number, email, url, image, file, select, checkbox, radio, true_false, relationship, etc.' ),
                                    'required'      => array( 'type' => 'boolean' ),
                                    'instructions'  => array( 'type' => 'string' ),
                                    'default_value' => array( 'type' => 'string' ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
            array(
                'name'        => 'acf_update_field_group',
                'description' => 'Update an existing ACF field group. Merges only provided fields. Requires manage_options + @leagueapps.com.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'key' ),
                    'properties' => array(
                        'key'             => array( 'type' => 'string',  'description' => 'ACF field group key — get from acf_list_field_groups' ),
                        'title'           => array( 'type' => 'string',  'description' => 'New title' ),
                        'location'        => array( 'type' => 'array',   'description' => 'New location rules' ),
                        'position'        => array( 'type' => 'string',  'description' => 'New position: normal, side, acf_after_title' ),
                        'label_placement' => array( 'type' => 'string',  'description' => 'New label placement: top or left' ),
                        'active'          => array( 'type' => 'boolean', 'description' => 'Enable or disable the group' ),
                    ),
                ),
            ),
            array(
                'name'        => 'acf_delete_field_group',
                'description' => 'Permanently delete an ACF field group and all its fields. Irreversible. Requires manage_options + @leagueapps.com.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'key' ),
                    'properties' => array(
                        'key' => array( 'type' => 'string', 'description' => 'ACF field group key — get from acf_list_field_groups' ),
                    ),
                ),
            ),
            // ACF Schema — Options Pages
            array(
                'name'        => 'acf_list_options_pages',
                'description' => 'List all ACF Pro options pages. Returns key, title, menu_slug, and parent_slug. Requires ACF Pro 6.2+, manage_options + @leagueapps.com.',
                'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            ),
            array(
                'name'        => 'acf_create_options_page',
                'description' => 'Create a new ACF Pro options page. Requires ACF Pro 6.2+, manage_options + @leagueapps.com.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'title', 'menu_slug' ),
                    'properties' => array(
                        'title'       => array( 'type' => 'string',  'description' => 'Options page title' ),
                        'menu_slug'   => array( 'type' => 'string',  'description' => 'Unique menu slug (URL-safe)' ),
                        'menu_title'  => array( 'type' => 'string',  'description' => 'Admin menu label. Defaults to title if omitted.' ),
                        'parent_slug' => array( 'type' => 'string',  'description' => 'Parent admin menu slug (e.g. "edit.php" to nest under Posts)' ),
                        'capability'  => array( 'type' => 'string',  'description' => 'Required capability. Default: edit_posts' ),
                        'redirect'    => array( 'type' => 'boolean', 'description' => 'Redirect to first child page. Default: false' ),
                        'description' => array( 'type' => 'string',  'description' => 'Optional description' ),
                    ),
                ),
            ),
            array(
                'name'        => 'acf_delete_options_page',
                'description' => 'Permanently delete an ACF Pro options page. Irreversible. Requires ACF Pro 6.2+, manage_options + @leagueapps.com.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'key' ),
                    'properties' => array(
                        'key' => array( 'type' => 'string', 'description' => 'Options page key — get from acf_list_options_pages' ),
                    ),
                ),
            ),
            // Beaver Builder
            array(
                'name'        => 'get_bb_global_colors',
                'description' => 'Get all Beaver Builder Global Style colors. Returns a label → hex map (e.g. "Primary": "e63946"). Requires manage_options.',
                'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            ),
            array(
                'name'        => 'update_bb_global_colors',
                'description' => 'Update one or more Beaver Builder Global Style colors by label. Flushes BB CSS cache automatically. Requires manage_options.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'colors' ),
                    'properties' => array(
                        'colors' => array(
                            'type'        => 'object',
                            'description' => 'Object of label → hex pairs. Label must match exactly (e.g. "Primary", "Accent", "Headings"). Hex with or without #. Example: {"Primary":"e63946","Accent":"457b9d"}',
                        ),
                    ),
                ),
            ),
            array(
                'name'        => 'bb_list_layout_templates',
                'description' => 'List available DS Launchpad layout templates by type (header/footer/home). Returns template titles and style numbers.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'type' => array( 'type' => 'string', 'description' => 'Filter by type: header, footer, or home. Omit to return all types.' ),
                    ),
                ),
            ),
            array(
                'name'        => 'bb_apply_layout_template',
                'description' => 'Replace the Beaver Builder content of "Header Main", "Footer Main" (Themer layouts), or the site front page with a DS Launchpad layout template. WARNING: replaces current content — requires confirm: true.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'type', 'style_number', 'confirm' ),
                    'properties' => array(
                        'type'         => array( 'type' => 'string',  'description' => 'Target to replace: header (→ Header Main themer layout), footer (→ Footer Main themer layout), or home (→ site front page).' ),
                        'style_number' => array( 'type' => 'integer', 'description' => 'Template number to apply. Header: 1–5, Footer: 1–3, Home: 1–6. Use bb_list_layout_templates to see what is available.' ),
                        'confirm'      => array( 'type' => 'boolean', 'description' => 'Must be true to proceed. Confirms the user understands the current layout content will be replaced.' ),
                    ),
                ),
            ),
            // Menus
            array(
                'name'        => 'list_menus',
                'description' => 'List all WordPress nav menus with their registered theme locations.',
                'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            ),
            array(
                'name'        => 'get_menu',
                'description' => 'Get a nav menu and its full item list (title, URL, parent, order, object type).',
                'inputSchema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'id'   => array( 'type' => 'integer', 'description' => 'Menu ID (use id or slug)' ),
                        'slug' => array( 'type' => 'string',  'description' => 'Menu slug (use id or slug)' ),
                    ),
                ),
            ),
            array(
                'name'        => 'set_menu_items',
                'description' => 'Replace all items in a nav menu with a new structure. WARNING: deletes existing items — requires confirm: true. Items are 0-indexed; use parent_index to reference a parent item by its position in the items array.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'items', 'confirm' ),
                    'properties' => array(
                        'id'      => array( 'type' => 'integer', 'description' => 'Menu ID (use id or slug)' ),
                        'slug'    => array( 'type' => 'string',  'description' => 'Menu slug (use id or slug)' ),
                        'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true. All existing items will be removed.' ),
                        'items'   => array(
                            'type'        => 'array',
                            'description' => 'Ordered array of menu items to create.',
                            'items'       => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'title'        => array( 'type' => 'string',  'description' => 'Menu item label' ),
                                    'url'          => array( 'type' => 'string',  'description' => 'URL for custom links' ),
                                    'object_id'    => array( 'type' => 'integer', 'description' => 'Post/page/CPT ID for post_type items' ),
                                    'object_type'  => array( 'type' => 'string',  'description' => 'Post type slug (e.g. page, post). Required if object_id is set.' ),
                                    'parent_index' => array( 'type' => 'integer', 'description' => '0-based index of the parent item in this items array (for nested menus)' ),
                                    'target'       => array( 'type' => 'string',  'description' => 'Link target, e.g. _blank' ),
                                    'classes'      => array( 'type' => 'string',  'description' => 'CSS class(es)' ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
            array(
                'name'        => 'assign_menu_to_location',
                'description' => 'Assign an existing menu to a registered theme location (e.g. primary-menu, header-menu).',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'menu_id', 'location' ),
                    'properties' => array(
                        'menu_id'  => array( 'type' => 'integer', 'description' => 'Menu ID — get from list_menus' ),
                        'location' => array( 'type' => 'string',  'description' => 'Theme location slug — get from list_menus registered_locations' ),
                    ),
                ),
            ),
            // Maintenance
            array(
                'name'        => 'flush_rewrite_rules',
                'description' => 'Flush WordPress rewrite rules (equivalent to saving Permalinks settings). Fixes 404s after adding/changing CPTs or taxonomies.',
                'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            ),
            array(
                'name'        => 'flush_cache',
                'description' => 'Flush the WordPress object cache.',
                'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            ),
            array(
                'name'        => 'delete_transients',
                'description' => 'Delete all WordPress transients (both regular and site transients) from the options table.',
                'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            ),
            array(
                'name'        => 'search_replace',
                'description' => 'Search and replace text in the WordPress database. Searches posts, postmeta, and options tables by default. WARNING: direct DB modification — requires confirm: true and manage_options + ' . DS_Env::staff_domain() . '.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'search', 'replace', 'confirm' ),
                    'properties' => array(
                        'search'  => array( 'type' => 'string',  'description' => 'Text to search for' ),
                        'replace' => array( 'type' => 'string',  'description' => 'Replacement text' ),
                        'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true. This operation cannot be undone.' ),
                        'tables'  => array( 'type' => 'array',   'description' => 'Optional: specific table names to search. Default: wp_posts, wp_postmeta, wp_options.' ),
                    ),
                ),
            ),
            // Options
            array(
                'name'        => 'get_option',
                'description' => 'Read a WordPress option by key (wp_options table). Requires manage_options + ' . DS_Env::staff_domain() . '.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'key' ),
                    'properties' => array(
                        'key' => array( 'type' => 'string', 'description' => 'Option name (e.g. blogname, siteurl, blogdescription)' ),
                    ),
                ),
            ),
            array(
                'name'        => 'update_option',
                'description' => 'Update a WordPress option by key. Requires manage_options + ' . DS_Env::staff_domain() . '.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'key', 'value' ),
                    'properties' => array(
                        'key'   => array( 'type' => 'string', 'description' => 'Option name' ),
                        'value' => array( 'description' => 'New value (string, number, boolean, or object)' ),
                    ),
                ),
            ),
            // Users
            array(
                'name'        => 'list_users',
                'description' => 'List WordPress users with optional role and search filters.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'role'     => array( 'type' => 'string',  'description' => 'Filter by role: administrator, editor, author, contributor, subscriber' ),
                        'search'   => array( 'type' => 'string',  'description' => 'Keyword search (name, email, login)' ),
                        'per_page' => array( 'type' => 'integer', 'description' => 'Results per page (max 200). Default: 50' ),
                        'offset'   => array( 'type' => 'integer', 'description' => 'Offset for pagination. Default: 0' ),
                    ),
                ),
            ),
            array(
                'name'        => 'get_user',
                'description' => 'Get a WordPress user by ID or email address.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'id'    => array( 'type' => 'integer', 'description' => 'User ID (use id or email)' ),
                        'email' => array( 'type' => 'string',  'description' => 'User email (use id or email)' ),
                    ),
                ),
            ),
            // Media
            array(
                'name'        => 'list_media',
                'description' => 'Search and list Media Library files. Filter by type, keyword, or the post they are attached to.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'search'      => array( 'type' => 'string',  'description' => 'Search by filename or title' ),
                        'mime_type'   => array( 'type' => 'string',  'description' => 'Filter by MIME type: image, video, audio, application/pdf, image/png, etc.' ),
                        'uploaded_to' => array( 'type' => 'integer', 'description' => 'Filter by the post/page ID the file is attached to' ),
                        'per_page'    => array( 'type' => 'integer', 'description' => 'Number of results (max 100). Default: 20' ),
                    ),
                ),
            ),
            array(
                'name'        => 'get_media',
                'description' => 'Get full details for a single Media Library item — URL, alt text, caption, dimensions, file size, and all registered image sizes.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'required'   => array( 'id' ),
                    'properties' => array(
                        'id' => array( 'type' => 'integer', 'description' => 'Attachment ID' ),
                    ),
                ),
            ),
            array(
                'name'        => 'regenerate_thumbnails',
                'description' => 'Regenerate image thumbnail sizes for Media Library attachments.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'ids'   => array( 'type' => 'array',   'description' => 'Optional: specific attachment IDs to regenerate. If omitted, processes the most recent images.' ),
                        'limit' => array( 'type' => 'integer', 'description' => 'Max number of images to process (max 100). Default: 20' ),
                    ),
                ),
            ),
        );
    }
}
