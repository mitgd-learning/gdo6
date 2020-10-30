<?php
namespace GDO\UI;

use GDO\Core\GDT;
use GDO\Core\WithFields;

/**
 * Simple collection of GDTs.
 * The render functions call the render function on all fields.
 * No template is used yet.
 * 
 * @author gizmore
 * @version 6.10
 * @since 6.07
 */
class GDT_Container extends GDT
{
    use WithFlex;
	use WithFields;
	use WithPHPJQuery;

	private function setupHTML()
	{
	    $this->addClass('gdt-container');
	    if ($this->flex)
	    {
	        $this->addClass('flx flx-'.$this->flexDirection);
	    }
	}
	
	public function renderCell()
	{
	    $this->setupHTML();
		$back = '<div '.$this->htmlAttributes().'>';
		foreach ($this->fields as $gdt)
		{
			$back .= $gdt->renderCell();
		}
		$back .= '</div>';
		return $back;
	}
	
	public function renderForm()
	{
	    $this->setupHTML();
	    $back = '<div '.$this->htmlAttributes().'>';
	    foreach ($this->fields as $gdt)
	    {
	        $back .= $gdt->renderForm();
	    }
	    $back .= '</div>';
	    return $back;
	}
	
	public function renderCard()
	{
	    $this->setupHTML();
	    $back = '<div '.$this->htmlAttributes().'>';
	    foreach ($this->fields as $gdt)
	    {
	        $back .= $gdt->renderCard();
	    }
	    $back .= '</div>';
	    return $back;
	}
	
}
