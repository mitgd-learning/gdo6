<?php
namespace GDO\DB;
use GDO\Table\WithOrder;
use GDO\Core\GDT_Template;
use GDO\UI\WithLabel;
use GDO\UI\WithIcon;
use GDO\Form\WithFormFields;
use GDO\UI\WithTooltip;
use GDO\Core\GDO;
use GDO\Core\GDT;
/**
 * Basic String type with database support.
 * @author gizmore
 * @version 7.00
 * @since 6.00
 */
class GDT_String extends GDT
{
    use WithLabel;
    use WithIcon;
    use WithFormFields;
    use WithTooltip;
    use WithOrder;
    use WithDatabase;
    
    const UTF8 = 1;
    const ASCII = 2;
    const BINARY = 3;
    
    public $pattern;
    public $encoding = self::UTF8;
    public $caseSensitive = false;
    
    public $min = 0;
    public $max = 255;
    
    public function utf8() { return $this->encoding(self::UTF8); }
    public function ascii() { return $this->encoding(self::ASCII); }
    public function binary() { return $this->encoding(self::BINARY); }
    public function isBinary() { return $this->encoding === self::BINARY; }
    
    public function encoding($encoding) { $this->encoding = $encoding; return $this; }
    
    public function pattern($pattern) { $this->pattern = $pattern; return $this; }
    public function htmlPattern() { return $this->pattern ? " pattern=\"{$this->htmlPatternValue()}\"" : ''; }
    public function htmlPatternValue() { return trim(rtrim($this->pattern, 'i'), $this->pattern[0].'^$'); }
    public function caseI($caseInsensitive=true) { return $this->caseS(!$caseInsensitive); }
    public function caseS($caseSensitive=true) { $this->caseSensitive = $caseSensitive; return $this; }
    
    public function min($min) { $this->min = $min; return $this; }
    public function max($max) { $this->max = $max; return $this; }
    
    public function getVar()
    {
        $var = trim(parent::getVar());
        return empty($var) ? null : $var;
    }
    
    public function setGDOData(GDO $gdo=null) { return $gdo && $gdo->hasVar($this->name) ? $this->val($gdo->getVar($this->name)) : $this; }
    
    ######################
    ### Table creation ###
    ######################
    public function gdoColumnDefine()
    {
        $charset = $this->gdoCharsetDefine();
        $collate = $this->gdoCollateDefine($this->caseSensitive);
        return "{$this->identifier()} VARCHAR({$this->max}) CHARSET $charset $collate{$this->gdoNullDefine()}";
    }
    
    public function gdoCharsetDefine()
    {
        switch ($this->encoding)
        {
            case self::UTF8: return 'utf8';
            case self::ASCII: return 'ascii';
            case self::BINARY: return 'binary';
        }
    }
    
    public function gdoCollateDefine($caseSensitive)
    {
        if ($this->isBinary())
        {
            return '';
        }
        $append = $caseSensitive ? '_bin' : '_general_ci';
        return 'COLLATE ' . $this->gdoCharsetDefine() . $append;
    }
    
    ##############
    ### Render ###
    ##############
    public function renderCell() { return html($this->getVar()); }
    public function renderForm() { return GDT_Template::php('DB', 'form/string.php', ['field' => $this]); }
    
    ################
    ### Validate ###
    ################
    public function validate($value)
    {
        if (parent::validate($value))
        {
            if ($value !== null)
            {
                $len = mb_strlen($value);
                if ( ($this->min !== null) && ($len < $this->min) )
                {
                    return $this->strlenError();
                }
                if ( ($this->max !== null) && ($len > $this->max) )
                {
                    return $this->strlenError();
                }
                if ( ($this->pattern !== null) && (!preg_match($this->pattern, $value)) )
                {
                    return $this->patternError();
                }
            }
            return true;
        }
    }
    
    private function patternError()
    {
        return $this->error('err_string_pattern');
    }
    
    private function strlenError()
    {
        if ( ($this->max !== null) && ($this->min !== null) )
        {
            return $this->error('err_strlen_between', [$this->min, $this->max]);
        }
        elseif ($this->max !== null)
        {
            return $this->error('err_strlen_too_large', [$this->max]);
        }
        elseif ($this->min !== null)
        {
            return $this->error('err_strlen_too_small', [$this->min]);
        }
    }
    
    ##############
    ### filter ###
    ##############
    public function renderFilter()
    {
        return GDT_Template::php('Type', 'filter/string.php', ['field'=>$this]);
    }
    
    public function filterQuery(Query $query)
    {
        if ('' !== ($filter = (string)$this->filterValue()))
        {
            $collate = $this->caseSensitive ? (' '.$this->gdoCollateDefine(false)) : '';
            $condition = sprintf('%s%s LIKE \'%%%s%%\'', $this->identifier(), $collate, GDO::escapeSearchS($filter));
            $this->filterQueryCondition($query, $condition);
        }
    }
    
    public function filterGDO(GDO $gdo)
    {
        if ($filter = $this->filterValue())
        {
            $pattern = chr(1).preg_quote($filter, chr(1)).chr(1);
            if (!$this->caseSensitive) { $pattern .= 'i'; } # Switch to case-i if necessary
            return !preg_match($pattern, $this->getVar());
        }
    }
}
