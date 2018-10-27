<?php /** @var $field \GDO\Date\GDT_Date **/
$id = 'date_'.$field->name; ?>
<div class="gdo-container<?=$field->classError()?>">
  <label for="<?=$id?>"><?=$field->defaultLabel()?></label>
  <?=$field->htmlIcon()?>
  <input
   id="<?=$id?>"
   type="date"
   name="form[<?= $field->name; ?>]"
   value="<?=$field->displayVar()?>" />
  <?=$field->htmlError()?>
</div>
