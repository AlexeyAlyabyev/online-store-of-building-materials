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

/* common/footer.twig */
class __TwigTemplate_e328af7bf2e57c47f35f8071d520406ba4a3c0b8b9a944bfb36e4d287a53e6c0 extends \Twig\Template
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
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        echo "<footer>
  <div class=\"container\"><a href=\"https://ocstore.com/?utm_source=ocstore3\" target=\"_blank\">";
        // line 2
        echo ($context["text_project"] ?? null);
        echo "</a>|<a href=\"https://docs.ocstore.com/?utm_source=ocstore3\" target=\"_blank\">";
        echo ($context["text_documentation"] ?? null);
        echo "</a>|<a href=\"https://opencartforum.com/?utm_source=ocstore3\" target=\"_blank\">";
        echo ($context["text_support"] ?? null);
        echo "</a><br />
    </div>
</footer>
</body></html>
";
    }

    public function getTemplateName()
    {
        return "common/footer.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  40 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "common/footer.twig", "");
    }
}
