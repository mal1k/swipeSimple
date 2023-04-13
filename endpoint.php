<?php
// создание маршрута
add_action( 'rest_api_init', function(){

	// пространство имен
	$namespace = 'swipe-simple';

	// маршрут
	$route = '/update';

	// параметры конечной точки (маршрута)
	$route_params = [
		'methods'  => 'POST',
		'callback' => 'swipeSimpleUpdate',
		'args'     => [
            'time' => [
                'type' => 'string',
                'required' => true
            ],
            'products' => [
                'type' => 'array',
                'required' => true
            ]
		],
		'permission_callback' => function( $request ){
            return true;
			// return is_user_logged_in();
		},
	];

	register_rest_route( $namespace, $route, $route_params );

} );

function getAttribute(string $attribute) {
    switch ($attribute) {
        case 'size':
            return ['Small', 'Medium', 'Large'];
        case 'gluten':
            return ['Regular', 'Gluten Free'];
        case 'instruction':
            return ['Custom', 'None']; # TODO for Custom: current price + 15%. Example: Variation price: 12$. Equal to 13,8$
        default:
            break;
    }
}

// функция обработчик конечной точки (маршрута)
function swipeSimpleUpdate(WP_REST_Request $request){

    $products = $request['products'];

    foreach ($products as $product) {
        create_custom_product_attribute($product);
    }
}

function create_custom_product_attribute($product){

    # variation with none instruction
    foreach ($product['variations'] as $key => $variation) {
        $variation['attributes'][] = [
            'name' => 'Instruction',
            'option' => 'None'
        ];
        $variations[] = $variation;
    }
    
    foreach ($product['variations'] as $key => $variation) {
        $variation['sku'] = '13' . $variation['sku'];
        $variation['regular_price'] += $variation['regular_price'] / 100 * 15;
        $variation['attributes'][] = [
            'name' => 'Instruction',
            'option' => 'Custom'
        ];
        $variations[] = $variation;
    }

	create_product_variation(
        array(
            'author'        => '',
            'title'         => $product['title'],
            'content'       => '<p>This is the product content <br>A very nice product, soft and clear…<p>',
            'excerpt'       => 'The product short description…',
            'regular_price' => '20', // product regular price
            'sale_price'    => '', // product sale price (optional)
            'stock'         => '1', // Set a minimal stock quantity
            'image_id'      => '', // optional
            'gallery_ids'   => array(), // optional
            'sku'           => '', // optional
            'tax_class'     => '', // optional
            'weight'        => '', // optional
            'attributes'    => array(
                'Size'   => getAttribute('size'),
                'Gluten'   =>  getAttribute('gluten'),
                'Instruction'   =>  getAttribute('instruction'),
            ),
        ),
        $variations
    );
}

function create_product_variation( $data, $variations ) {
	 if( ! function_exists ('add_custom_attribute') ) return;
	 $postname = sanitize_title( $data['title'] );
	 $author = empty( $data['author'] ) ? '1' : $data['author'];

     $args = array(
        'limit' => -1,
        'name' => $postname
    );
    $query = new WC_Product_Query( $args );
    $products = $query->get_products();
    if ( $products ) {
        $new_item = false;
        $product_id = $products[0]->id;
    } else {
        $new_item = true;
    }

	 $post_data = array(
		 'post_author'   => $author,
		 'post_name'     => $postname,
		 'post_title'    => $data['title'],
		 'post_content'  => $data['content'],
		 'post_excerpt'  => $data['excerpt'],
		 'post_status'   => ($data['status']) ? 'publish' : 'draft',
		 'ping_status'   => 'closed',
		 'post_type'     => 'product',
		 'guid'          => home_url( '/product/'.$postname.'/' ),
	 );
	 // Creating the product (post data) # if not found
     if ( $new_item ) {
         $product_id = wp_insert_post( $post_data );
     }

	 // Get an instance of the WC_Product_Variable object and save it
	 $product = new WC_Product_Variable( $product_id );
	 $product->save();
	 ## ---------------------- Other optional data  ---------------------- ##
	 // IMAGES GALLERY
	 if( ! empty( $data['gallery_ids'] ) && count( $data['gallery_ids'] ) > 0 )
		 $product->set_gallery_image_ids( $data['gallery_ids'] );
	 // SKU
	 if( ! empty( $data['sku'] ) )
		 $product->set_sku( $data['sku'] );
	 // STOCK (stock will be managed in variations)
	 $product->set_stock_quantity( $data['stock'] ); // Set a minimal stock quantity
	 $product->set_manage_stock(true);
	 $product->set_stock_status('');
	 // Tax class
	 if( empty( $data['tax_class'] ) )
		 $product->set_tax_class( $data['tax_class'] );
	 // WEIGHT
	 if( ! empty($data['weight']) )
		 $product->set_weight(''); // weight (reseting)
	 else
		 $product->set_weight($data['weight']);
	 $product->validate_props(); // Check validation
	 ## ---------------------- VARIATION ATTRIBUTES ---------------------- ##
	 $product_attributes = array();
	 foreach( $data['attributes'] as $key => $terms ){
		 $attr_name = ucfirst($key);
		 $attr_slug = sanitize_title($key);
		 $taxonomy = wc_attribute_taxonomy_name(wp_unslash($key));
		 // NEW Attributes: Register and save them
		 
		 if (taxonomy_exists($taxonomy))
		 {
			 $attribute_id = wc_attribute_taxonomy_id_by_name($attr_slug);   
		 } else{
			 $attribute_id = add_custom_attribute($attr_name);
		 }
		 
		 $product_attributes[$taxonomy] = array (
			 'name'         => $taxonomy,
			 'value' 		=> '',
			 'position'     => '',
			 'is_visible'   => 1,
			 'is_variation' => 1,
			 'is_taxonomy'  => 1,
			 
		 );
		 if($attribute_id){
			 // Iterating through the variations attributes
			 foreach ($terms as $term_name )
			 {
				 $taxonomy = 'pa_'.$attr_slug; // The attribute taxonomy
		 
				 if( ! taxonomy_exists( $taxonomy ) ) {
					 register_taxonomy(
						 $taxonomy,
					 'product_variation',
						 array(
							 'hierarchical' => false,
							 'label' => $attr_name,
							 'query_var' => true,
							 'rewrite' => array( 'slug' => $attr_slug), // The base slug
						 ),
					 );
				 }

				 // Check if the Term name exist and if not we create it.
				 if( ! term_exists( $term_name, $taxonomy ) ) {
					 wp_insert_term( $term_name, $taxonomy ); // Create the term
				 }
				 // Get the post Terms names from the parent variable product.
				 $post_term_names =  wp_get_post_terms( $product_id, $taxonomy, array('fields' => 'names') );
				 // Check if the post term exist and if not we set it in the parent variable product.
				 if( ! in_array( $term_name, $post_term_names ) )
					 wp_set_post_terms( $product_id, $term_name, $taxonomy, true );
				 // Set the attribute data in the product variation
				 //update_post_meta($variation_id, 'attribute_'.$taxonomy, $term_slug );
			 }
		 }
	 }
	 update_post_meta( $product_id, '_product_attributes', $product_attributes ); 

    // CREATING VARIABLES
    $attributes = array(
        array(
            "name" => "Size",
            "options" => getAttribute('size'),
            "position" => 1,
            "visible" => 1,
            "variation" => 1
        ),
        array(
            "name" => "Gluten",
            "options" => getAttribute('gluten'),
            "position" => 2,
            "visible" => 1,
            "variation" => 1
        ),
        array(
            "name" => "Instruction",
            "options" => getAttribute('instruction'),
            "position" => 3,
            "visible" => 1,
            "variation" => 1
        )
    );

    if ($attributes) {
        $productAttributes = array();
        foreach ($attributes as $attribute) {
            $objProduct = new WC_Product_Variable($product_id);
            $attr = wc_sanitize_taxonomy_name(stripslashes($attribute["name"])); 
            $attr = 'pa_'.$attr;
            
            if ($attribute["options"])
                foreach ($attribute["options"] as $option)
                    wp_set_object_terms($product_id,$option,$attr,true);
                
            $productAttributes[sanitize_title($attr)] = array(
                'name' => sanitize_title($attr),
                'value' => $attribute["options"],
                'position' => $attribute["position"],
                'is_visible' => $attribute["visible"],
                'is_variation' => $attribute["variation"],
                'is_taxonomy' => '1'
            );
            $objProduct->save();
        }
        update_post_meta($product_id,'_product_attributes',$productAttributes);

        if ( !empty($variations) ) {
            foreach($variations as $variation) {
                try {
                    $objVariation = new WC_Product_Variation();

                    if ( !empty($variation['sku']) ) {
                        $variation_id = wc_get_product_id_by_sku($variation['sku']);
                        if ( !empty( $variation_id ) )
                            $objVariation = wc_get_product($variation_id);
                    }

                    $objVariation->set_price(0);
                    $objVariation->set_regular_price($variation["regular_price"]);
                    // $objVariation->set_parent_id($product_id);
                    $objVariation->set_parent_id($product_id);
                    if (isset($variation["sku"]) && $variation["sku"]) {
                        $objVariation->set_sku($variation["sku"]);
                    }
                    $objVariation->set_manage_stock(0); // 1 or 0
                    $objVariation->set_stock_quantity(1);
                    $objVariation->set_stock_status('instock');
                    $var_attributes = array();
                    foreach ($variation["attributes"] as $vattribute) {
                        $taxonomy = "pa_".wc_sanitize_taxonomy_name(stripslashes($vattribute["name"]));
                        $attr_val_slug =  wc_sanitize_taxonomy_name(stripslashes($vattribute["option"]));
                        $var_attributes[$taxonomy]=$attr_val_slug;
                    }
                    $objVariation->set_attributes($var_attributes);
                    $objVariation->save();
                } catch (\Throwable $th) {
                    die($th->getMessage());
                }
            }
        }
    }

}


 /*
 * Register a global woocommerce product add attribute Class.
 *
 * @param str   $nam | name of attribute
 * @param arr   $vals | array of variations
 * 
 */
 function add_custom_attribute($nam) {
	 $attrs = array();      
	 $attributes = wc_get_attribute_taxonomies(); 
	 $slug = sanitize_title($nam);
	 foreach ($attributes as $key => $value) {
		 array_push($attrs,$attributes[$key]->attribute_name);                    
	 } 
	 if (!in_array( $nam, $attrs ) ) {          
		 $args = array(
			 'slug'    => $slug,
			 'name'   => __( $nam, 'woocommerce' ),
			 'type'    => 'select',
			 'orderby' => 'menu_order',
			 'has_archives'  => false,
		 );                    
		 return wc_create_attribute($args);
	 }               
 }
 