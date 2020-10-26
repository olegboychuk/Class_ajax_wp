<?php

add_action( 'wp_loaded', array ( 'T5_Ajax_Search', 'init' ) );

/**
 * Ajaxify the search form.
 */
class T5_Ajax_Search{
    /**
     * The main instance. You can create further instances for unit tests.
     * @type object
     */
    protected static $instance = NULL;

    /**
     * Action name used by AJAX callback handlers
     * @type string
     */
    protected $action = 't5_ajax_search';

    /**
     * Handler for initial load.
     *
     * @wp-hook wp_loaded
     * @return void
     */
    public static function init(){
        NULL === self::$instance and self::$instance = new self;
        return self::$instance;
    }

    /**
     * Constructor. Registers the actions.
     *
     *  @wp-hook wp_loaded
     *  @return object
     */
    public function __construct(){
        $callback = array ( $this, 'search' );
        add_action( 'wp_ajax_'        . $this->action, $callback );
        add_action( 'wp_ajax_nopriv_' . $this->action, $callback );
        add_action( 'wp_enqueue_scripts', array ( $this, 'register_script' ) );
    }

    /**
     * Callback for AJAX search.
     *
     * @wp-hook wp_ajax_t5_ajax_search
     * @wp-hook wp_ajax_nopriv_t5_ajax_search
     * @return void
     */
    public function search(){
        // print_r($_POST);
        // print_r($_REQUEST);
        switch($_REQUEST['fn']) {
            case 'firstinitdata':
                $output = $this->get_start_data( $_REQUEST['pt'],$_REQUEST['tax'] );
                break;
            case 'area-search':
                $this->get_term_names( $_REQUEST['search_term'], $_REQUEST['tax'] );
                break;
            case 'name-search':
                $this->get_post_names( $_REQUEST['search_term'],$_REQUEST['tax'],$_REQUEST['cpt'],$_REQUEST['selectBY'] );
                break;
            case 'cosmo-search':
                $this->get_cosmo( $_REQUEST['area_term'],$_REQUEST['name_term'],$_REQUEST['tax'],$_REQUEST['cpt'] );
                break;
            default:
                $output = 'That is not a valid FN parameter. Please check your string and try again';
                break;
        }

        $output = json_encode($output);
        if( is_array($output) ) {
            return $output;
        }
        else
            echo $output;
        exit;

    }

    /**
     * Fetch names of terms from cstm  taxonomies
     *
     * @param array  Array of $terms names
     * @return void
     */
    protected function get_term_names( $s,$tax ){
        $args  = array ( 's' => $s ,'taxonomy' => $tax );
        $termargs  = apply_filters( 'term_area_ajax_search_args', $args );
        $terms = get_terms($termargs);
        if ( $terms ){
            $this->render_search_results( $terms );
        }else{
            print '<b>nothing found</b>';
        }
        exit;
    }

    /**
     * Fetch names of terms from cstm  taxonomies
     *
     * @param array  Array of $terms names
     * @return void
     */
    protected function get_post_names( $s,$tax,$cpt,$select_by=false ){
        global $wpdb;
        print_r($select_by);

        if($select_by){
            $args = array(
                'post_status' => 'publish',
                'post_type' => $cpt,
                's' => $s,
                'tax_query' => array(
                    array(
                        'taxonomy'    => $tax,
                        'fields'      => 'names', // get only the term names  
                        'name__like'  => $select_by
                    )
                )
            );
            $posts = get_posts( $args );
        }else{
            $posts = $wpdb->get_results("SELECT `ID`, `post_title` FROM " . $wpdb->prefix . "posts WHERE `post_title` LIKE '%" . $s . "%' AND post_type = '" . $cpt . "' AND `post_status` = 'publish' ORDER BY `post_title` DESC");
        }

        // print_r($posts);
        // print_r($args);

        if ( $posts ){
            $this->render_search_results( $posts );
        }else{
            print '<b>nothing found</b>';
        }
        exit;
    }

    /**
     * Fetch names of terms from cstm  taxonomies
     *
     * @param array  Array of $terms names
     * @return void
     */
    protected function get_start_data( $pt,$tax ){
        global $wpdb;

        $args  = array ( 's' => '' ,'taxonomy' => $tax );
        $termargs  = apply_filters( 'term_area_ajax_search_args', $args );
        $terms_name = get_terms($termargs);
        
        // $cities = array();
        // foreach( $terms_name as $index => $ter_mname ) {
        //     $titles [$index] = $post['post_title'];
        //     get_term_by('name', $val ,$tax);
        //     $cities
        // }
        

        // Return null if we found no results
        if ( ! $terms_name ){
            return;
        }
        
        // A sql query to return all post titles
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s and post_status = 'publish'", $pt ), ARRAY_A );
        
        // Return null if we found no results
        if ( ! $results ){
            return;
        }
            
        $titles = array();
        foreach( $results as $index => $post ) {
            $titles [$index] = $post['post_title'];
        }

        $data = array( 
            'cities'=> $terms_name,
            'names'=> $titles 
        );
        return $data;
    }

    /**
     * Create markup from $posts
     *
     * @param array $posts Array of post objects
     * @return void
     */
    protected function render_search_results( $name_array_strngs ){
        print '<ul class="t5-ajax-search-results">';
        foreach ( $name_array_strngs as $name ){
            printf('<li>%1$s</li>',esc_html( $name ));
        }
        print '</ul>';
    }


    /**
     * get posts by first parameter and return full markup
     *
     * @param array  Array of $terms names
     * @return void
     */
    protected function get_cosmo( $s1,$s2,$tax,$cpt ){
        
        if($s1!='' && $s2 != ''){//both param
            $args  = array ( 's1' => $s1, 's2'=> $s2 ,'taxonomy' => $tax,'post_type'=>$cpt );
            $args  = apply_filters( 'args_filter_by_both_param', $args );
        }elseif($s1 !='' && $s2 == ''){//by_first_param
            $args  = array ( 's1' => $s1,'taxonomy' => $tax,'post_type'=>$cpt );
            $args  = apply_filters( 'args_filter_by_first_param', $args );
        }elseif($s1 =='' && $s2 != ''){//by_second_param
            $args  = array ('s2'=>$s2 ,'taxonomy' => $tax,'post_type'=>$cpt );
            $args  = apply_filters( 'args_filter_by_second_param', $args );
        }else{//withot_param
            $args  = array ('taxonomy' => $tax,'post_type'=>$cpt, 'post_status'=>'publish');
        }
        //print_r($args);

        $loop = new WP_Query($args);
        ob_start();
        if( $loop->have_posts() ):
            while( $loop->have_posts() ): $loop->the_post();
                get_template_part('parts/cs_holder');
            endwhile;
        else:
            echo '<h3>'.printf(__('Searched for %s and %s but didn\'t find anything'),$s1,$s2).'</h3>';  
        endif; wp_reset_postdata();
        $content = ob_get_contents();
        ob_end_clean();
        echo $content;
    }

    /**
     * Register script and local variables.
     *
     * @wp-hook wp_enqueue_scripts
     * @return void
     */
    public function register_script(){
        
		$template_url = get_template_directory_uri();

        wp_enqueue_script( 'script-search', $template_url . '/js/script-search.js', array ( 'jquery' ), NULL, TRUE );
        
        wp_localize_script(
            't5-ajax',
            'T5Ajax',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'action'  => $this->action
            )
        );
    }
}
