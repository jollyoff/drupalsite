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

/* modules/custom/drupalsite_core/templates/ds-social-network.html.twig */
class __TwigTemplate_23525effaa55687d0b1aa0c28a9765a186bdef8dfb67227bdda8a5284c010da7 extends \Twig\Template
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
        // line 1
        $context["cls"] = "ds-social-network";
        // line 2
        echo "<nav class=\"";
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["cls"] ?? null), 2, $this->source), "html", null, true);
        echo "\">
  <ul class=\"";
        // line 3
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["cls"] ?? null), 3, $this->source), "html", null, true);
        echo "-list\">
    <li class=\"";
        // line 4
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["cls"] ?? null), 4, $this->source), "html", null, true);
        echo "-item\">
      <a class=\"";
        // line 5
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["cls"] ?? null), 5, $this->source), "html", null, true);
        echo "-link\" href=\"https://facebook.com\" target=\"_blank\">
        <img src=\"themes/custom/drupalsite/assets/images/facebook_png.png\">
      </a>
    </li>
    <li class=\"";
        // line 9
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["cls"] ?? null), 9, $this->source), "html", null, true);
        echo "-item\">
      <a class=\"";
        // line 10
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["cls"] ?? null), 10, $this->source), "html", null, true);
        echo "-link\" href=\"https://instagram.com\" target=\"_blank\">
        <img src=\"themes/custom/drupalsite/assets/images/instagram_png.png\">
      </a>
    </li>
    <li class=\"";
        // line 14
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["cls"] ?? null), 14, $this->source), "html", null, true);
        echo "-item\">
      <a class=\"";
        // line 15
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["cls"] ?? null), 15, $this->source), "html", null, true);
        echo "-link\" href=\"https://twitter.com\" target=\"_blank\">
        <img src=\"themes/custom/drupalsite/assets/images/twitter_png.png\">
      </a>
    </li>
    <li class=\"";
        // line 19
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["cls"] ?? null), 19, $this->source), "html", null, true);
        echo "-item\">
      <a class=\"";
        // line 20
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["cls"] ?? null), 20, $this->source), "html", null, true);
        echo "-link\" href=\"https://youtube.com\" target=\"_blank\">
        <img src=\"themes/custom/drupalsite/assets/images/youtube_png.png\">
      </a>
    </li>
  </ul>
</nav>
";
    }

    public function getTemplateName()
    {
        return "modules/custom/drupalsite_core/templates/ds-social-network.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  87 => 20,  83 => 19,  76 => 15,  72 => 14,  65 => 10,  61 => 9,  54 => 5,  50 => 4,  46 => 3,  41 => 2,  39 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "modules/custom/drupalsite_core/templates/ds-social-network.html.twig", "C:\\xampp\\htdocs\\drupalsite\\modules\\custom\\drupalsite_core\\templates\\ds-social-network.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = array("set" => 1);
        static $filters = array("escape" => 2);
        static $functions = array();

        try {
            $this->sandbox->checkSecurity(
                ['set'],
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
