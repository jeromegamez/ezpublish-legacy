{* DO NOT EDIT THIS FILE! Use an override template instead. *}
{default attribute_base=ContentObjectAttribute}

<div class="block">
{*<fieldset>*}
{def $multi_price = $attribute.content
     $custom_price_list = $multi_price.custom_price_list
     $auto_price_list = $multi_price.auto_price_list
     $currency_list = $multi_price.auto_currency_list}
    {if or( count( $custom_price_list ), count( $auto_price_list ) )}
        <table class="list" cellspacing="0">
        <tr>
            <th class="tight">&nbsp;</th>
            <th>{'Currency'|i18n( 'design/standard/content/datatype' )}</th>
            <th>{'Value'|i18n( 'design/standard/content/datatype' )}</th>
        </tr>
            {foreach $auto_price_list as $price}
            <tr>
                {* Remove. *}
                <td><input type="checkbox" name="{$attribute_base}_remove_price_array_{$attribute.id}[{$price.currency_code}]" title="{'Select price for removal.'|i18n( 'design/standard/content/datatype' )}" disabled="disabled" /></td>

                {* Currency *}
                <td>{$price.currency_code}</td>

                {* Value *}
                <td>{$price.value}({'Auto'|i18n( 'design/standard/content/datatype' )})</td>
            </tr>
            {/foreach}
            {foreach $custom_price_list as $price}
            <tr>
                {* Remove. *}
                <td><input type="checkbox" name="{$attribute_base}_remove_price_array_{$attribute.id}[{$price.currency_code}]" title="{'Select price for removal.'|i18n( 'design/standard/content/datatype' )}" /></td>

                {* Currency *}
                <td>{$price.currency_code}</td>

                {* Value *}
                <td><input type="text" name="{$attribute_base}_price_array_{$attribute.id}[{$price.currency_code}]" size="12" value="{$price.value}" /></td>
            </tr>
            {/foreach}
        </table>

        {* 'Remove' button *}
        <input class="button" type="submit" name="CustomActionButton[{$attribute.id}_remove_prices]" value="{'Remove selected'|i18n( 'design/standard/content/datatype' )}" title="{'Remove selected prices.'|i18n( 'design/standard/content/datatype' )}" /><p>
    {else}
        {* Disabled 'remove' button *}
        {'Price list is empty'|i18n( 'design/standard/content/datatype' )}<p>
        <input class="button-disabled" type="submit" name="CustomActionButton[{$attribute.id}_remove_prices]" value="{'Remove selected'|i18n( 'design/standard/content/datatype' )}" disabled="disabled" /><p>
    {/if}

    {if count( $currency_list )}
        {* Select currency *}
        <select name="{$attribute_base}_selected_currency_{$attribute.id}" title="Select currency">
            {foreach $currency_list as $currency}
                <option value="{$currency.code}">{$currency.code}</option>
            {/foreach}
            {*
            {section var=Currency loop=$currency_list}
                <option value="{$Currency.code}">{$Currency.code}</option>
            {/section}
            *}
        </select>

        {* 'Set price' button *}
        <input class="button" type="submit" name="CustomActionButton[{$attribute.id}_set_custom_price]" value="{'Set custom price'|i18n( 'design/standard/content/datatype' )}" title="{'Set custom price.'|i18n( 'design/standard/content/datatype' )}" />
        <input name="ContentObjectAttribute_id[]" value="{$attribute.id}" type="hidden">
    {else}
        {* Disabled 'Set price' button *}
        <input class="button-disabled" type="submit" name="CustomActionButton[{$attribute.id}_set_custom_price]" value="{'Set custom price'|i18n( 'design/standard/content/datatype' )}" disabled="disabled" />
        {'There are no available currencies.'|i18n( 'design/standard/content/datatype' )}
    {/if}
{undef}
{*</fieldset>*}
</div>

<div class="block">
<label>{'VAT'|i18n( 'design/standard/content/datatype' )}:</label>
<select name="{$attribute_base}_ezmultiprice_inc_ex_vat_{$attribute.id}">
<option value="1" {section show=eq( $attribute.content.is_vat_included, true() )}selected="selected"{/section}>{'Price inc. VAT'|i18n( 'design/standard/content/datatype' )}</option>
<option value="2" {section show=eq( $attribute.content.is_vat_included, false() )}selected="selected"{/section}>{'Price ex. VAT'|i18n( 'design/standard/content/datatype' )}</option>
</select>
</div>

<div class="block">
<label>{'VAT type'|i18n( 'design/standard/content/datatype' )}:</label>
{include uri='design:shop/vattype/edit.tpl'
         select_name=concat( $attribute_base, "_ezmultiprice_vat_id_", $attribute.id )
         vat_types=$attribute.content.vat_type
         current_val=$attribute.content.selected_vat_type.id}
</div>

{/default}
