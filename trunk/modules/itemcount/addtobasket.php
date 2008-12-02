<?php

include_once("kernel/classes/ezcontentobject.php");
include_once( "kernel/classes/ezbasket.php" );
include_once( 'kernel/shop/classes/ezshopfunctions.php' );

// iniate objects
$http 		=& eZHttpTool::instance();

// fetch object id from params
$objectID 	= $Params['ProductObjectID']; 

// if no product id exists
if(!$objectID)
{
	// fetch it from post variable
	$objectID = $http->postVariable('ProductObjectID');
}

// fetch item count
$itemCount = $Params['ItemCount']; 

// if no item count is specified
if(!$itemCount)
{
	// get item count from post data
	$itemCount = $http->postVariable('ItemCount');
	
	// if there still doesn't exist a count
	if(!$itemCount)
	{
		// set default item count
		$itemCount = 1;		
	}
}

// set redirect URI
$redirectURI = 'shop/basket';

$object = eZContentObject::fetch( $objectID );
$nodeID = $object->attribute( 'main_node_id' );
$price = 0.0;
$isVATIncluded = true;
$attributes = $object->contentObjectAttributes();

$priceFound = false;

foreach ( $attributes as $attribute )
{
    $dataType = $attribute->dataType();
    if ( eZShopFunctions::isProductDatatype( $dataType->isA() ) )
    {
        $priceObj =& $attribute->content();
        $price += $priceObj->attribute( 'price' );
        $priceFound = true;
    }
}

if ( !$priceFound )
{
    eZDebug::writeError( 'Attempted to add object without price to basket.' );
    return array( 'status' => EZ_MODULE_OPERATION_CANCELED );
}
$basket =& eZBasket::currentBasket();

/* Check if the item with the same options is not already in the basket: */
$itemID = false;
$collection =& eZProductCollection::fetch( $basket->attribute( 'productcollection_id' ) );
if ( $collection )
{
    $count = 0;
    /* Calculate number of options passed via the HTTP variable: */
    foreach ( array_keys( $optionList ) as $key )
    {
        if ( is_array( $optionList[$key] ) )
            $count += count( $optionList[$key] );
        else
            $count++;
    }
    $collectionItems =& $collection->itemList( false );
    foreach ( $collectionItems as $item )
    {
        /* For all items in the basket which have the same object_id: */
        if ( $item['contentobject_id'] == $objectID )
        {
            $options =& eZProductCollectionItemOption::fetchList( $item['id'], false );
            /* If the number of option for this item is not the same as in the HTTP variable: */
            if ( count( $options ) != $count )
            {
                break;
            }
            $theSame = true;
            foreach ( $options as $option )
            {
                /* If any option differs, go away: */
                if ( ( is_array( $optionList[$option['object_attribute_id']] ) &&
                       !in_array( $option['option_item_id'], $optionList[$option['object_attribute_id']] ) )
                     || ( !is_array( $optionList[$option['object_attribute_id']] ) &&
                          $option['option_item_id'] != $optionList[$option['object_attribute_id']] ) )
                {
                    $theSame = false;
                    break;
                }
            }
            if ( $theSame )
            {
                $itemID = $item['id'];
                break;
            }
        }
    }
}

if ( $itemID )
{
    /* If found in the basket, just increment number of that items: */
    $item =& eZProductCollectionItem::fetch( $itemID );
    $item->setAttribute( 'item_count', $itemCount + $item->attribute( 'item_count' ) );
    $item->store();
}
else
{
    $item =& eZProductCollectionItem::create( $basket->attribute( "productcollection_id" ) );

	$dataMap = $object->dataMap();
	$itemName = $dataMap['navn']->content();

    // $item->setAttribute( 'name', $object->attribute( 'name' ) );
    $item->setAttribute( 'name', $itemName );
    $item->setAttribute( "contentobject_id", $objectID );
    $item->setAttribute( "item_count", $itemCount );
    $item->setAttribute( "price", $price );
    if ( $priceObj->attribute( 'is_vat_included' ) )
    {
        $item->setAttribute( "is_vat_inc", '1' );
    }
    else
    {
        $item->setAttribute( "is_vat_inc", '0' );
    }
    $item->setAttribute( "vat_value", $priceObj->attribute( 'vat_percent' ) );
    $item->setAttribute( "discount", $priceObj->attribute( 'discount_percent' ) );
    $item->store();
    $priceWithoutOptions = $price;

    $optionIDList = array();
    foreach ( array_keys( $optionList ) as $key )
    {
        $attributeID = $key;
        $optionString = $optionList[$key];
        if ( is_array( $optionString ) )
        {
            foreach ( $optionString as $optionID )
            {
                $optionIDList[] = array( 'attribute_id' => $attributeID,
                                         'option_string' => $optionID );
            }
        }
        else
        {
            $optionIDList[] = array( 'attribute_id' => $attributeID,
                                     'option_string' => $optionString );
        }
    }

    $db =& eZDB::instance();
    $db->begin();
    foreach ( $optionIDList as $optionIDItem )
    {
        $attributeID = $optionIDItem['attribute_id'];
        $optionString = $optionIDItem['option_string'];

        $attribute =& eZContentObjectAttribute::fetch( $attributeID, $object->attribute( 'current_version' ) );
        $dataType =& $attribute->dataType();
        $optionData = $dataType->productOptionInformation( $attribute, $optionString, $item );
        if ( $optionData )
        {
            $optionItem =& eZProductCollectionItemOption::create( $item->attribute( 'id' ), $optionData['id'], $optionData['name'],
                                                                  $optionData['value'], $optionData['additional_price'], $attributeID );
            $optionItem->store();
            $price += $optionData['additional_price'];
        }
    }

    if ( $price != $priceWithoutOptions )
    {
        $item->setAttribute( "price", $price );
        $item->store();
    }
    $db->commit();
}

// redirect
$module =& $Params["Module"];
$module->redirectTo( $redirectURI );

?>
