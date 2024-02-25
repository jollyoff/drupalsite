<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;

/* modules/custom/drupalsite_core/layouts/twocol_section/ds-article.html.twig */
class __TwigTemplate_3829b68e44fcfa7da70100cee8b0276a932eadef8e17293aaedc54ffc394aa3d extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->env->getExtension('\Twig\Extension\SandboxExtension');
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $context["classes"] = [0 => "layout", 1 => "lt-ds-article"];
        // line 7
        if (($context["content"] ?? null)) {
            // line 8
            echo "  <div";
            echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["attributes"] ?? null), "addClass", [0 => ($context["classes"] ?? null)], "method", false, false, true, 8), 8, $this->source), "html", null, true);
            echo ">

    ";
            // line 10
            if (twig_get_attribute($this->env, $this->source, ($context["content"] ?? null), "content", [], "any", false, false, true, 10)) {
                // line 11
                echo "      <div ";
                echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["region_attributes"] ?? null), "content", [], "any", false, false, true, 11), "addClass", [0 => "layout__region", 1 => "lt-ds-article-content"], "method", false, false, true, 11), 11, $this->source), "html", null, true);
                echo ">
        ";
                // line 12
                echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["content"] ?? null), "content", [], "any", false, false, true, 12), 12, $this->source), "html", null, true);
                echo "
      </div>
    ";
            }
            // line 15
            echo "
    ";
            // line 16
            if (twig_get_attribute($this->env, $this->source, ($context["content"] ?? null), "sidebar", [], "any", false, false, true, 16)) {
                // line 17
                echo "      <div ";
                echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["region_attributes"] ?? null), "sidebar", [], "any", false, false, true, 17), "addClass", [0 => "layout__region", 1 => "lt-ds-article-sidebar"], "method", false, false, true, 17), 17, $this->source), "html", null, true);
                echo ">
        ";
                // line 18
                echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["content"] ?? null), "sidebar", [], "any", false, false, true, 18), 18, $this->source), "html", null, true);
                echo "
      </div>
    ";
            }
            // line 21
            echo "
  </div>
";
        }
    }

    public function getTemplateName()
    {
        return "modules/custom/drupalsite_core/layouts/twocol_section/ds-article.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  78 => 21,  72 => 18,  67 => 17,  65 => 16,  62 => 15,  56 => 12,  51 => 11,  49 => 10,  43 => 8,  41 => 7,  39 => 2,);
    }

    public function getSourceContext()
    {
        return new Source("", "modules/custom/drupalsite_core/layouts/twocol_section/ds-article.html.twig", "/opt/drupal/web/modules/custom/drupalsite_core/layouts/twocol_section/ds-article.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = array("set" => 2, "if" => 7);
        static $filters = array("escape" => 8);
        static $functions = array();

        try {
            $this->sandbox->checkSecurity(
                ['set', 'if'],
                ['escape'],
                []
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
