<?php
namespace GDO\DB;
use GDO\Core\GDT;
use GDO\UI\WithIcon;
use GDO\UI\WithLabel;
use GDO\Form\WithFormFields;
use GDO\Core\GDT_Template;
/**
 * ENUMs are similiar to a select, but only allow 1 item being chosen.
 * For the database, and enum column will be created.
 * @author gizmore
 * @since 5.00
 * @version 7.00
 */
class GDT_Enum extends GDT
{
    use WithIcon;
    use WithLabel;
    use WithDatabase;
    use WithFormFields;

    ############
    ### Base ###
    ############
    public function gdoColumnDefine()
    {
        $values = implode(',', array_map(array('GDO\Core\GDO', 'quoteS'), $this->enumValues));
        return "{$this->identifier()} ENUM ($values){$this->gdoNullDefine()}{$this->gdoInitialDefine()}";
    }
    
    public function renderForm() { return GDT_Template::php('Form', 'form/enum.php', ['field' => $this]); }
    public function renderCell() { return $this->enumLabel($this->getVar()); }
    public function toJSON()
    {
        return array(
            'enumValues' => $this->enumValues,
            'selected' => $this->getVar(),
        );
    }
    
    public function toValue($var)
    {
        return $var === $this->emptyValue ? null : $var;
    }
    
    ############
    ### Enum ###
    ############
    public $enumValues;
    public function enumLabel($enumValue=null) { return $enumValue ? t("enum_$enumValue") : $enumValue; }
    public function enumValues(...$enumValues) { $this->enumValues = $enumValues; return $this; }
    public function enumIndex() { $index = array_search($this->getVar(), $this->enumValues, true); return $index === false ? 0 : $index + 1; }
    public function enumForId($index) { return $this->enumValues[$index-1]; }
    public function htmlSelected($enumValue) { return $this->getVar() === $enumValue ? ' selected="selected"' : ''; }
    
    public $emptyValue = '0';
    public function emptyValue($emptyValue)
    {
        $this->emptyValue = $emptyValue;
        return $this->emptyLabel(t('please_choice'));
    }
    public $emptyLabel;
    public function emptyLabel($emptyLabel)
    {
        $this->emptyLabel = $emptyLabel;
        return $this;
    }
    
    ##############
    ### Filter ###
    ##############
    /**
     * Render select filter header.
     */
    public function renderFilter() { return GDT_Template::php('Form', 'filter/enum.php', ['field' => $this]); }
    
    /**
     * Filter value is an array.
     */
    public function filterValue()
    {
        if ($filter = parent::filterValue())
        {
            if ($filter = is_array($filter) ? $filter : json_decode($filter))
            {
                return $filter;
            }
        }
        return [];
    }
    
    public function displayFilterValue() { return $this->filterValue(); }
    
    /**
     * Add where clause to query on filter.
     */
    public function filterQuery(Query $query)
    {
        $filter = $this->filterValue();
        if (!empty($filter))
        {
            $filter = array_map(['GDO\\DB\\GDO', 'escapeS'], $filter);
            $condition = sprintf('%s IN ("%s")', $this->identifier(), implode('","', $filter));
            $this->filterQueryCondition($query, $condition);
        }
    }
    
    ################
    ### Validate ###
    ################
    public function validate($value)
    {
        if (parent::validate($value))
        {
            if ($value !== null)
            {
                if (!in_array($value, $this->enumValues, true))
                {
                    return $this->error('err_invalid_choice');
                }
            }
            return true;
        }
    }
}
