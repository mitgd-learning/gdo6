<?php /** @var $field \GDO\DB\GDT_Object **/
/** @var \GDO\Core\GDO $gdo **/
$gdo = $field->getValue();
$id = 'gwfac_'.$field->name; ?>
<div class="gdo-container<?= $field->classError(); ?>"
 ng-app="gdo6"
 ng-controller="GDOAutoCompleteCtrl"
 ng-init='init(<?= $field->displayJSON(); ?>, "#<?= $id; ?>")'>
  <?= $field->htmlIcon(); ?>
  <label for="form[<?= $field->name; ?>]"><?= $field->displayLabel(); ?></label>
  <input
   type="text"
   <?= $field->htmlRequired(); ?>
   <?= $field->htmlDisabled(); ?>
   id="<?= $id; ?>"
   name="form[<?= $field->name; ?>]"
   value="<?=$field->displayVar()?>" />
  <input type="hidden" name="nocompletion_<?=$field->name?>" value="1" />
  <?= $field->htmlError(); ?>
</div>
