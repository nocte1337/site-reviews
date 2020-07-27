<?php

namespace GeminiLabs\SiteReviews\Modules\Html;

use GeminiLabs\SiteReviews\Defaults\FieldDefaults;
use GeminiLabs\SiteReviews\Helper;
use GeminiLabs\SiteReviews\Helpers\Arr;
use GeminiLabs\SiteReviews\Helpers\Cast;
use GeminiLabs\SiteReviews\Helpers\Str;
use ReflectionClass;
use ReflectionMethod;

/**
 * This class generates raw HTML tags without additional DOM markup
 * 
 * @method string a(string|array ...$params)
 * @method string button(string|array ...$params)
 * @method string div(string|array ...$params)
 * @method string i(string|array ...$params)
 * @method string img(string|array ...$params)
 * @method string label(string|array ...$params)
 * @method string p(string|array ...$params)
 * @method string select(string|array ...$params)
 * @method string span(string|array ...$params)
 */
class Builder
{
    const INPUT_TYPES = [
        'checkbox', 'date', 'datetime-local', 'email', 'file', 'hidden', 'image', 'month',
        'number', 'password', 'radio', 'range', 'reset', 'search', 'submit', 'tel', 'text', 'time',
        'url', 'week',
    ];

    const TAGS_FORM = [
        'input', 'select', 'textarea',
    ];

    const TAGS_SINGLE = [
        'img',
    ];

    const TAGS_STRUCTURE = [
        'div', 'form', 'nav', 'ol', 'section', 'ul',
    ];

    const TAGS_TEXT = [
        'a', 'button', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'i', 'label', 'li', 'option', 'p', 'pre',
        'small', 'span',
    ];

    /**
     * @var \GeminiLabs\SiteReviews\Arguments
     */
    public $args;

    /**
     * @var bool
     */
    public $render = false;

    /**
     * @var string
     */
    public $tag;

    /**
     * @var string
     */
    public $type;

    /**
     * @param string $method
     * @param array $methodArgs
     * @return string|void
     */
    public function __call($method, $methodArgs)
    {
        $instance = new static();
        $args = call_user_func_array([$instance, 'prepareArgs'], $methodArgs);
        $tag = Str::dashCase($method);
        $result = $instance->build($tag, $args);
        if (!$instance->render) {
            return $result;
        }
        echo $result;
    }

    /**
     * @param string $property
     * @param mixed $value
     * @return void
     */
    public function __set($property, $value)
    {
        $method = Helper::buildMethodName($property, 'set');
        if (method_exists($this, $method)) {
            call_user_func([$this, $method], $value);
        }
    }

    /**
     * @return string
     */
    public function build($tag, array $args = [])
    {
        $this->setArgs($args, $tag);
        $this->setTag($tag);
        glsr()->action('builder', $this);
        $result = $this->isHtmlTag($this->tag)
            ? $this->buildElement()
            : $this->buildCustom($tag);
        return glsr()->filterString('builder/result', $result, $this);
    }

    /**
     * @return void|string
     */
    public function buildClosingTag()
    {
        return '</'.$this->tag.'>';
    }

    /**
     * @return string
     */
    public function buildDefaultElement($text = '')
    {
        $text = Helper::ifEmpty($text, $this->args->text, $strict = true);
        return $this->buildOpeningTag().$text.$this->buildClosingTag();
    }

    /**
     * @return void|string
     */
    public function buildElement()
    {
        if (in_array($this->tag, static::TAGS_SINGLE)) {
            return $this->buildOpeningTag();
        }
        if (in_array($this->tag, static::TAGS_FORM)) {
            $method = Helper::buildMethodName($this->tag, 'buildForm');
            return $this->$method().$this->buildFieldDescription();
        }
        return $this->buildDefaultElement();
    }

    /**
     * @param string $tag
     * @return void|string
     */
    public function buildCustom($tag)
    {
        if (class_exists($className = $this->getFieldClassName($tag))) {
            return (new $className($this))->build();
        }
        glsr_log()->error("Field [$className] missing.");
    }

    /**
     * @return void|string
     */
    public function buildOpeningTag()
    {
        $attributes = glsr(Attributes::class)->{$this->tag}($this->args->toArray())->toString();
        return '<'.trim($this->tag.' '.$attributes).'>';
    }

    /**
     * @return string
     */
    public function raw(array $field)
    {
        unset($field['label']);
        return $this->{$field['type']}($field);
    }

    /**
     * @param array $args
     * @param string $type
     * @return void
     */
    public function setArgs($args = [], $type = '')
    {
        $args = Arr::consolidate($args);
        if (!empty($args)) {
            $args = $this->normalize($args, $type);
            $args = glsr(FieldDefaults::class)->merge($args);
        }
        $args = glsr()->filterArray('builder/'.$type.'/args', $args, $this);
        $this->args = glsr()->args($args);
    }

    /**
     * @param bool $bool
     * @return void
     */
    public function setRender($bool)
    {
        $this->render = Cast::toBool($bool);
    }

    /**
     * @param string $tag
     * @return void
     */
    public function setTag($tag)
    {
        $tag = Cast::toString($tag);
        $this->tag = Helper::ifTrue(in_array($tag, static::INPUT_TYPES), 'input', $tag);
    }

    /**
     * @return string|void
     */
    protected function buildFieldDescription()
    {
        if (!empty($this->args->description)) {
            return $this->p([
                'class' => 'description',
                'text' => $this->args->description,
            ]);
        }
    }

    /**
     * @return string|void
     */
    protected function buildFormInput()
    {
        if (!in_array($this->args->type, ['checkbox', 'radio'])) {
            if (isset($this->args->multiple)) {
                $this->args->set('name', Str::suffix($this->args->name, '[]'));
            }
            return $this->buildFormLabel().$this->buildOpeningTag();
        }
        return empty($this->args->options)
            ? $this->buildFormInputChoice()
            : $this->buildFormInputMultiChoice();
    }

    /**
     * @return string|void
     */
    protected function buildFormInputChoice()
    {
        $this->args->set('label', Helper::ifEmpty($this->args->text, $this->args->label));
        return $this->buildFormLabel([
            'text' => $this->buildOpeningTag().' '.$this->args->label,
        ]);
    }

    /**
     * @return string|void
     */
    protected function buildFormInputMultiChoice()
    {
        $index = 0;
        $options = array_reduce(array_keys($this->args->cast('options', 'array')), function ($carry, $key) use (&$index) {
            $index++;
            $field = $this->input([
                'checked' => Cast::toString($key) === $this->args->cast('value', 'string'),
                'id' => Helper::ifTrue(!empty($this->args->id), $this->args->id.'-'.$index),
                'label' => $this->args->options[$key],
                'name' => $this->args->name,
                'type' => $this->args->type,
                'value' => $key,
            ]);
            return $carry.$this->li($field);
        });
        return $this->ul($options, [
            'class' => $this->args->class,
            'id' => $this->args->id,
        ]);
    }

    /**
     * @return void|string
     */
    protected function buildFormLabel(array $customArgs = [])
    {
        if (!empty($this->args->label) && 'hidden' !== $this->args->type) {
            return $this->label(wp_parse_args($customArgs, [
                'for' => $this->args->id,
                'text' => $this->args->label,
            ]));
        }
    }

    /**
     * @return string|void
     */
    protected function buildFormSelect()
    {
        return $this->buildFormLabel().$this->buildDefaultElement($this->buildFormSelectOptions());
    }

    /**
     * @return string|void
     */
    protected function buildFormSelectOptions()
    {
        return array_reduce(array_keys($this->args->cast('options', 'array')), function ($carry, $key) {
            return $carry.$this->option([
                'selected' => $this->args->cast('value', 'string') === Cast::toString($key),
                'text' => $this->args->options[$key],
                'value' => $key,
            ]);
        });
    }

    /**
     * @return string|void
     */
    protected function buildFormTextarea()
    {
        return $this->buildFormLabel().$this->buildDefaultElement($this->args->cast('value', 'string'));
    }

    /**
     * @param string $tag
     * @return bool
     */
    protected function isHtmlTag($tag)
    {
        return in_array($tag, array_merge(
            static::TAGS_FORM,
            static::TAGS_SINGLE,
            static::TAGS_STRUCTURE,
            static::TAGS_TEXT
        ));
    }

    /**
     * @return bool
     */
    protected function isMultiField(array $args)
    {
        $args = glsr()->args($args);
        if ('checkbox' === $args->type && count($args->cast('options', 'array')) > 1) {
            return true;
        }
        return Helper::ifTrue(isset($args->multiple), true, false);
    }

    /**
     * @param string $type
     * @return string
     */
    protected function getFieldClassName($tag)
    {
        $className = Helper::buildClassName($tag, __NAMESPACE__.'\Fields');
        return glsr()->filterString('builder/field/'.$tag, $className);
    }

    /**
     * @return array
     */
    protected function normalize(array $args, $type)
    {
        if (class_exists($className = $this->getFieldClassName($type))) {
            $args = $className::merge($args);
        }
        if ($this->isMultiField($args)) {
            $args['name'] .= '[]';
        }
        return $args;
    }

    /**
     * @param string|array ...$params
     * @return array
     */
    protected function prepareArgs(...$params)
    {
        if (is_array($parameter1 = array_shift($params))) {
            return $parameter1;
        }
        $parameter2 = Arr::consolidate(array_shift($params));
        if (is_scalar($parameter1)) {
            $parameter2['text'] = $parameter1;
        }
        return $parameter2;
    }
}
