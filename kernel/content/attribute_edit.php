<?php
//
// Created on: <17-Apr-2002 10:34:48 bf>
//
// Copyright (C) 1999-2005 eZ systems as. All rights reserved.
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE included in
// the packaging of this file.
//
// Licencees holding a valid "eZ publish professional licence" version 2
// may use this file in accordance with the "eZ publish professional licence"
// version 2 Agreement provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" version 2 is available at
// http://ez.no/ez_publish/licences/professional/ and in the file
// PROFESSIONAL_LICENCE included in the packaging of this file.
// For pricing of this licence please contact us via e-mail to licence@ez.no.
// Further contact information is available at http://ez.no/company/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//

/*!
  \file attribute_edit.php
  This file is a shared code file which is used by different parts of the system
  to edit objects. This file only implements editing of attributes and uses
  hooks to allow external code to add functionality.
  \param $Module must be set by the code which includes this file
*/

include_once( 'kernel/classes/ezcontentclass.php' );
include_once( 'kernel/classes/ezcontentclassattribute.php' );

include_once( 'kernel/classes/ezcontentobject.php' );
include_once( 'kernel/classes/ezcontentobjectversion.php' );
include_once( 'kernel/classes/ezcontentobjectattribute.php' );

include_once( 'lib/ezutils/classes/ezhttptool.php' );

include_once( 'kernel/common/template.php' );

include_once( 'kernel/classes/ezpreferences.php' );

if ( isset( $Module ) )
    $Module =& $Params['Module'];
$ObjectID =& $Params['ObjectID'];
if ( !isset( $EditVersion ) )
    $EditVersion =& $Params['EditVersion'];

if ( !isset( $EditLanguage ) and
     isset( $Params['EditLanguage'] ) )
    $EditLanguage = $Params['EditLanguage'];
if ( !isset( $EditLanguage ) or
     !is_string( $EditLanguage ) or
     strlen( $EditLanguage ) == 0 )
    $EditLanguage = false;

if ( !isset( $FromLanguage ) and
     isset( $Params['FromLanguage'] ) )
    $FromLanguage = $Params['FromLanguage'];
if ( !isset( $FromLanguage ) or
     !is_string( $FromLanguage ) or
     strlen( $FromLanguage ) == 0 )
    $FromLanguage = false;

if ( $FromLanguage == $EditLanguage )
    $FromLanguage = false;


if ( $Module->runHooks( 'pre_fetch', array( $ObjectID, &$EditVersion, &$EditLanguage, &$FromLanguage ) ) )
    return;

$object =& eZContentObject::fetch( $ObjectID );
if ( $object === null )
    return $Module->handleError( EZ_ERROR_KERNEL_NOT_AVAILABLE, 'kernel' );


$version =& $object->version( $EditVersion );
$classID = $object->attribute( 'contentclass_id' );

$attributeDataBaseName = 'ContentObjectAttribute';


$class =& eZContentClass::fetch( $classID );
$contentObjectAttributes =& $version->contentObjectAttributes( $EditLanguage );
if ( $contentObjectAttributes === null or
     count( $contentObjectAttributes ) == 0 )
    $contentObjectAttributes =& $version->contentObjectAttributes();

$fromContentObjectAttributes = false;
$isTranslatingContent = false;
if ( $FromLanguage !== false )
{
    $isTranslatingContent = true;
    $fromContentObjectAttributes =& $version->contentObjectAttributes( $FromLanguage );
    if ( $fromContentObjectAttributes === null or
         count( $fromContentObjectAttributes ) == 0 )
    {
        unset( $fromContentObjectAttributes );
        $fromContentObjectAttributes = false;
        $isTranslatingContent = false;
    }
}

$http =& eZHTTPTool::instance();

$validation = array( 'processed' => false,
                     'attributes' => array(),
                     'placement' => array( ) );

if ( $Module->runHooks( 'post_fetch', array( &$class, &$object, &$version, &$contentObjectAttributes, $EditVersion, $EditLanguage, $FromLanguage, &$validation ) ) )
    return;

// Checking if object has at least one placement, if not user needs to choose it from browse page
$assignments =& $version->attribute( 'parent_nodes' );
if ( count( $assignments ) < 1 && $Module->isCurrentAction( 'Publish' ) )
{
    $Module->setCurrentAction( 'BrowseForNodes' );
}



// Custom Action Code Start
$customAction = false;
$customActionAttributeArray = array();
// Check for custom actions
if ( $http->hasPostVariable( "CustomActionButton" ) )
{
    $customActionArray = $http->postVariable( "CustomActionButton" );
    foreach ( $customActionArray as $customActionKey => $customActionValue )
    {
        $customActionString = $customActionKey;

        if ( preg_match( "#^([0-9]+)_(.*)$#", $customActionString, $matchArray ) )
        {
            $customActionAttributeID = $matchArray[1];
            $customAction = $matchArray[2];
            $customActionAttributeArray[$customActionAttributeID] = array( 'id' => $customActionAttributeID,
                                                                           'value' => $customAction );
        }
    }
}

$storeActions = array( 'Preview',
                       'Translate',
                       'VersionEdit',
                       'Apply',
                       'Publish',
                       'Store',
                       'StoreExit',
//                      'Discard',
//                      'CustomAction',
                       'EditLanguage',
                       'BrowseForObjects',
                       'NewObject',
                       'BrowseForNodes',
                       'RemoveAssignments',
                       'DeleteRelation',
                       'DeleteNode',
                       'MoveNode' );
$storingAllowed = in_array( $Module->currentAction(), $storeActions );
if ( $http->hasPostVariable( 'CustomActionButton' ) )
    $storingAllowed = true;

$hasObjectInput = true;
if ( $http->hasPostVariable( 'HasObjectInput' ) )
    $hasObjectInput =  $http->postVariable( 'HasObjectInput' );

// These variables will be modified according to validation
$inputValidated = true;
$requireFixup = false;
$validatedAttributes = array();

if ( $storingAllowed && $hasObjectInput)
{
    // Disable checking 'is_required' flag for some actions.
    $validationParameters = array( 'skip-isRequired' => false );
    if ( $Module->isCurrentAction( 'Store' ) )
    {
        $validationParameters['skip-isRequired'] = true;
    }

    // Validate input
    include_once( 'lib/ezutils/classes/ezinputvalidator.php' );
    $validationResult = $object->validateInput( $contentObjectAttributes, $attributeDataBaseName, false, $validationParameters );
    $unvalidatedAttributes = $validationResult['unvalidated-attributes'];
    $validatedAttributes = $validationResult['validated-attributes'];
    $inputValidated = $validationResult['input-validated'];

    // Fixup input
    if ( $validationResult['require-fixup'] )
        $object->fixupInput( $contentObjectAttributes, $attributeDataBaseName );

    // If no redirection uri we assume it's content/edit
    if ( !isset( $currentRedirectionURI ) )
        $currentRedirectionURI = $Module->redirectionURI( 'content', 'edit', array( $ObjectID, $EditVersion, $EditLanguage ) );

    $fetchResult = $object->fetchInput( $contentObjectAttributes, $attributeDataBaseName,
                                        $customActionAttributeArray,
                                        array( 'module' => &$Module,
                                               'current-redirection-uri' => $currentRedirectionURI ) );
    $attributeInputMap =& $fetchResult['attribute-input-map'];
    if ( $Module->isCurrentAction( 'Discard' ) )
        $inputValidated = true;

    if ( $inputValidated and count( $attributeInputMap ) > 0 )
    {
        if ( $Module->runHooks( 'pre_commit', array( &$class, &$object, &$version, &$contentObjectAttributes, $EditVersion, $EditLanguage, $FromLanguage ) ) )
            return;
        $version->setAttribute( 'modified', time() );
        $version->setAttribute( 'status', EZ_VERSION_STATUS_DRAFT );
        $version->store();
//         print( "storing<br/>" );
        // Tell attributes to store themselves if necessary
        $object->storeInput( $contentObjectAttributes,
                             $attributeInputMap );
    }

    $validation['processed'] = true;
    $validation['attributes'] = $unvalidatedAttributes;

    $object->setName( $class->contentObjectName( $object ), $version->attribute( 'version' ), $EditLanguage );
}
elseif ( $storingAllowed )
{
    if ( !isset( $currentRedirectionURI ) )
        $currentRedirectionURI = $Module->redirectionURI( 'content', 'edit', array( $ObjectID, $EditVersion, $EditLanguage ) );
    eZContentObject::recursionProtectionStart();
    foreach( array_keys( $contentObjectAttributes ) as $key )
    {
        $contentObjectAttribute =& $contentObjectAttributes[$key];
        $object->handleCustomHTTPActions( $contentObjectAttribute,  $attributeDataBaseName,
                                          $customActionAttributeArray,
                                          array( 'module' => &$Module,
                                                 'current-redirection-uri' => $currentRedirectionURI ) );
        $contentObjectAttribute->setContent( $contentObjectAttribute->attribute( 'content' ) );
    }
    eZContentObject::recursionProtectionEnd();
}

if ( $Module->isCurrentAction( 'Publish' ) )
{
    $mainFound = false;
    $assignments =& $version->attribute( 'parent_nodes' );
    foreach ( array_keys( $assignments ) as $key )
    {
        if ( $assignments[$key]->attribute( 'is_main' ) == 1 )
        {
            $mainFound = true;
            break;
        }
    }
    if ( !$mainFound and count( $assignments ) > 0 )
    {
        /*
        $contentINI =& eZINI::instance( 'content.ini' );
        if ( $contentINI->variable( 'EditSettings', 'EmbedNodeAssignmentHandling' ) == 'enabled' )
        {
            $validation[ 'placement' ][] = array( 'text' => ezi18n( 'kernel/content', 'No main node selected, please select one.' ) );
            $validation[ 'processed' ] = true;
            $inputValidated = false;
            eZDebugSetting::writeDebug( 'kernel-content-edit', "placement is not validated" );
        }
        */

        if( eZPreferences::value( 'admin_edit_show_locations' ) == '0' )
        {
            $validation[ 'placement' ][] = array( 'text' => ezi18n( 'kernel/content', 'No main node selected, please select one.' ) );
            $validation[ 'processed' ] = true;
            $inputValidated = false;
            eZDebugSetting::writeDebug( 'kernel-content-edit', "placement is not validated" );
        }
    }
    else
        eZDebugSetting::writeDebug( 'kernel-content-edit', "placement is validated" );

}

// After the object has been validated we can check for other actions
$Result = '';
if ( $inputValidated == true )
{
    if ( $validatedAttributes == null )
    {
        if ( $Module->runHooks( 'action_check', array( &$class, &$object, &$version, &$contentObjectAttributes, $EditVersion, $EditLanguage, $FromLanguage, &$Result  ) ) )
            return;
        eZDebug::writeDebug( $Result, 'Result' );
        if ( $Result != '' )
            return;
    }
}

if ( isset( $Params['TemplateObject'] ) )
    $tpl =& $Params['TemplateObject'];

if ( !isset( $tpl ) || get_class( $tpl ) != 'eztemplate' )
    $tpl =& templateInit();

$tpl->setVariable( 'validation', $validation );
$tpl->setVariable( 'validation_log', $validatedAttributes );


$Module->setTitle( 'Edit ' . $class->attribute( 'name' ) . ' - ' . $object->attribute( 'name' ) );
$res =& eZTemplateDesignResource::instance();

$assignments =& $version->attribute( 'parent_nodes' );
$mainAssignment = false;
foreach ( array_keys( $assignments ) as $key )
{
    if ( $assignments[$key]->attribute( 'is_main' ) == 1 )
    {
        $mainAssignment =& $assignments[$key];
        break;
    }
}

$res->setKeys( array( array( 'object', $object->attribute( 'id' ) ),
                      array( 'class', $class->attribute( 'id' ) ),
                      array( 'class_identifier', $class->attribute( 'identifier' ) )
                      ) );

if ( $mainAssignment )
{
    $parentNode =& $mainAssignment->attribute( 'parent_node_obj' );
    if ( $parentNode )
    {
        $parentObject =& $parentNode->attribute( 'object' );
        if ( $parentObject )
        {
            $parentClass =& $parentObject->attribute( 'content_class' );
            if ( $parentClass )
            {
                $res->setKeys( array( array( 'parent_class', $parentClass->attribute( 'id' ) ),
                                      array( 'parent_class_identifier', $parentClass->attribute( 'identifier' ) )
                                      ) );
            }
        }
    }
}

if ( !isset( $OmitSectionSetting ) )
    $OmitSectionSetting = false;
if ( $OmitSectionSetting !== true )
{
    include_once( 'kernel/classes/ezsection.php' );
    eZSection::setGlobalID( $object->attribute( 'section_id' ) );
}

$contentObjectDataMap = array();
foreach ( array_keys( $contentObjectAttributes ) as $contentObjectAttributeKey )
{
    $contentObjectAttribute =& $contentObjectAttributes[$contentObjectAttributeKey];
    $contentObjectAttributeIdentifier = $contentObjectAttribute->attribute( 'contentclass_attribute_identifier' );
    $contentObjectDataMap[$contentObjectAttributeIdentifier] =& $contentObjectAttribute;
}

$tpl->setVariable( 'edit_version', $EditVersion );
$tpl->setVariable( 'edit_language', $EditLanguage );
$tpl->setVariable( 'from_language', $FromLanguage );
$tpl->setVariable( 'content_version', $version );
$tpl->setVariable( 'http', $http );
$tpl->setVariable( 'content_attributes', $contentObjectAttributes );
$tpl->setVariable( 'from_content_attributes', $fromContentObjectAttributes );
$tpl->setVariable( 'is_translating_content', $isTranslatingContent );
$tpl->setVariable( 'content_attributes_data_map', $contentObjectDataMap );
$tpl->setVariable( 'class', $class );
$tpl->setVariable( 'object', $object );
$tpl->setVariable( 'attribute_base', $attributeDataBaseName );

if ( $Module->runHooks( 'pre_template', array( &$class, &$object, &$version, &$contentObjectAttributes, $EditVersion, $EditLanguage, &$tpl, $FromLanguage ) ) )
    return;

$templateName = 'design:content/edit.tpl';

if ( isset( $Params['TemplateName'] ) )
    $templateName = $Params['TemplateName'];

$Result = array();
$Result['content'] =& $tpl->fetch( $templateName );
// $Result['path'] = array( array( 'text' => ezi18n( 'kernel/content', 'Content' ),
//                                 'url' => false ),
//                          array( 'text' => ezi18n( 'kernel/content', 'Edit' ),
//                                 'url' => false ),
//                          array( 'text' => $object->attribute( 'name' ),
//                                 'url' => false ) );

$path = array();
$titlePath = array();

$hasPath = false;
if ( $mainAssignment )
{
    $parentNode =& $mainAssignment->attribute( 'parent_node_obj' );
    if ( $parentNode )
    {
        $parents =& $parentNode->attribute( 'path' );

        foreach ( $parents as $parent )
        {
            $path[] = array( 'text' => $parent->attribute( 'name' ),
                             'url' => '/content/view/full/' . $parent->attribute( 'node_id' ),
                             'url_alias' => $parent->attribute( 'url_alias' ),
                             'node_id' => $parent->attribute( 'node_id' )
                             );
        }

        if ( $parentNode->attribute( 'name' ) != null )
        {
            $path[] = array( 'text' => $parentNode->attribute( 'name' ),
                             'url' => '/content/view/full/' . $parentNode->attribute( 'node_id' ),
                             'url_alias' => $parentNode->attribute( 'url_alias' ),
                             'node_id' => $parentNode->attribute( 'node_id' ) );
        }

        $objectPathElement = array( 'text' => $object->attribute( 'name' ),
                                    'url' => false,
                                    'url_alias' => false );
        $existingNode = $object->attribute( 'main_node' );
        if ( $existingNode )
        {
            $objectPathElement['url'] = '/content/view/full/' . $existingNode->attribute( 'node_id' );
            $objectPathElement['url_alias'] = $existingNode->attribute( 'url_alias' );
            $objectPathElement['node_id'] = $existingNode->attribute( 'node_id' );
        }
        $path[] = $objectPathElement;
        $hasPath = true;
    }
}
if ( !$hasPath )
{
    $existingNode = $object->attribute( 'main_node' );
    if ( $existingNode )
    {
        $parents =& $existingNode->attribute( 'path' );

        foreach ( $parents as $parent )
        {
            $path[] = array( 'text' => $parent->attribute( 'name' ),
                             'url' => '/content/view/full/' . $parent->attribute( 'node_id' ),
                             'url_alias' => $parent->attribute( 'url_alias' ),
                             'node_id' => $parent->attribute( 'node_id' )
                             );
        }
        $path[] = array( 'text' => $existingNode->attribute( 'name' ),
                         'url' => '/content/view/full/' . $existingNode->attribute( 'node_id' ),
                         'url_alias' => $existingNode->attribute( 'url_alias' ),
                         'node_id' => $existingNode->attribute( 'node_id' ) );
        $hasPath = true;
    }
}
if ( !$hasPath )
{
    $path[] = array( 'text' => $object->attribute( 'name' ),
                     'url' => false );
}

$Result['path'] = $path;

include_once( 'kernel/classes/ezsection.php' );
$section =& eZSection::fetch( $object->attribute( 'section_id' ) );
if ( $section )
    $Result['navigation_part'] = $section->attribute( 'navigation_part_identifier' );

?>
