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

/* themes/custom/drupalsite/templates/field/comment.html.twig */
class __TwigTemplate_2cf7d1f2694e2f373e421ee7df89642e2ea7fa3b36725efea76025bd3099bdc4 extends \Twig\Template
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
        // line 71
        echo "
<article";
        // line 72
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["attributes"] ?? null), "addClass", [0 => "js-comment"], "method", false, false, true, 72), 72, $this->source), "html", null, true);
        echo ">
  ";
        // line 78
        echo "  <div class=\"information-comment\">
    <div class=\"user_comment_picture\">";
        // line 79
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["user_picture"] ?? null), 79, $this->source), "html", null, true);
        echo "</div>
  <p>Автор: ";
        // line 80
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["author"] ?? null), 80, $this->source), "html", null, true);
        echo "</p>
  <p>Дата: ";
        // line 81
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, twig_date_format_filter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["comment"] ?? null), "created", [], "method", false, false, true, 81), 81, $this->source), "d.m.Y"), "html", null, true);
        echo "</p>
  </div>
  ";
        // line 83
        if (($context["submitted"] ?? null)) {
            // line 84
            echo "    <footer>
      ";
            // line 85
            if (($context["parent"] ?? null)) {
                // line 86
                echo "        <p class=\"visually-hidden\">";
                echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["parent"] ?? null), 86, $this->source), "html", null, true);
                echo "</p>
      ";
            }
            // line 88
            echo "
    </footer>
  ";
        }
        // line 91
        echo "
  <div class=\"comment-content\" ";
        // line 92
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["content_attributes"] ?? null), 92, $this->source), "html", null, true);
        echo ">
    ";
        // line 93
        if (($context["title"] ?? null)) {
            // line 94
            echo "      ";
            echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["title_prefix"] ?? null), 94, $this->source), "html", null, true);
            echo "
      ";
            // line 95
            echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["title_suffix"] ?? null), 95, $this->source), "html", null, true);
            echo "
    ";
        }
        // line 97
        echo "    ";
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["content"] ?? null), 97, $this->source), "html", null, true);
        echo "
  </div>
</article>
";
    }

    public function getTemplateName()
    {
        return "themes/custom/drupalsite/templates/field/comment.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  99 => 97,  94 => 95,  89 => 94,  87 => 93,  83 => 92,  80 => 91,  75 => 88,  69 => 86,  67 => 85,  64 => 84,  62 => 83,  57 => 81,  53 => 80,  49 => 79,  46 => 78,  42 => 72,  39 => 71,);
    }

    public function getSourceContext()
    {
        return new Source("", "themes/custom/drupalsite/templates/field/comment.html.twig", "/opt/drupal/web/themes/custom/drupalsite/templates/field/comment.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = array("if" => 83);
        static $filters = array("escape" => 72, "date" => 81);
        static $functions = array();

        try {
            $this->sandbox->checkSecurity(
                ['if'],
                ['escape', 'date'],
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
