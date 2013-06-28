<?php
namespace CRM\CivixBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use CRM\CivixBundle\Builder\Collection;
use CRM\CivixBundle\Builder\Dirs;
use CRM\CivixBundle\Builder\Info;
use CRM\CivixBundle\Builder\Menu;
use CRM\CivixBundle\Builder\Module;
use CRM\CivixBundle\Builder\Template;
use CRM\CivixBundle\Utils\Path;
use Exception;

class AddFormCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('generate:form')
            ->setDescription('Add a basic web form to a CiviCRM Module-Extension')
            ->addArgument('<ClassName>', InputArgument::REQUIRED, 'Base name of the form class name (eg "MyForm")')
            ->addArgument('<web/path>', InputArgument::REQUIRED, 'The path which maps to this form (eg "civicrm/my-form")')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!preg_match('/^civicrm\//', $input->getArgument('<web/path>'))) {
            throw new Exception("Web form path must begin with 'civicrm/'");
        }
        if (!preg_match('/^[A-Z][A-Za-z0-9_]*$/', $input->getArgument('<ClassName>'))) {
            throw new Exception("Class name should be valid (alphanumeric beginning with uppercase)");
        }

        $ctx = array();
        $ctx['type'] = 'module';
        $ctx['basedir'] = rtrim(getcwd(),'/');
        $ctx['formClassName'] = $input->getArgument('<ClassName>');
        $basedir = new Path($ctx['basedir']);

        $info = new Info($basedir->string('info.xml'));
        $info->load($ctx);
        $attrs = $info->get()->attributes();
        if ($attrs['type'] != 'module') {
            $output->writeln('<error>Wrong extension type: '. $attrs['type'] . '</error>');
            return;
        }

        if (preg_match('/^CRM_/', $input->getArgument('<ClassName>')) || preg_match('/_Form_/', $input->getArgument('<ClassName>'))) {
            $prefix = strtr($ctx['namespace'], '/', '_') . '_Form_';
            throw new Exception("Class name looks suspicious. Please note the final class will be automatically prefixed with \"{$prefix}\"");
        }

        $dirs = new Dirs(array(
            $basedir->string('xml','Menu'),
            $basedir->string($ctx['namespace'],'Form'),
            $basedir->string('templates', $ctx['namespace'],'Form'),
        ));
        $dirs->save($ctx, $output);

        $menu = new Menu($basedir->string('xml', 'Menu', $ctx['mainFile'] . '.xml'));
        $menu->loadInit($ctx);
        if (!$menu->hasPath($input->getArgument('<web/path>'))) {
            $fullClass = implode('_', array($ctx['namespace'], 'Form', $input->getArgument('<ClassName>')));
            $fullClass = preg_replace(':/:', '_', $fullClass);
            $menu->addItem($ctx, $input->getArgument('<ClassName>'), $fullClass, $input->getArgument('<web/path>'));
            $menu->save($ctx, $output);
        } else {
            $output->writeln(sprintf('<error>Failed to bind %s to class %s; %s is already bound</error>',
                $input->getArgument('<web/path>'),
                $input->getArgument('<ClassName>'),
                $input->getArgument('<web/path>')
            ));
        }

        $phpFile = $basedir->string($ctx['namespace'], 'Form', $ctx['formClassName'] . '.php');
        if (!file_exists($phpFile)) {
            $output->writeln(sprintf('<info>Write %s</info>', $phpFile));
            file_put_contents($phpFile, $this->getContainer()->get('templating')->render('CRMCivixBundle:Code:form.php.php', $ctx));
        } else {
            $output->writeln(sprintf('<error>Skip %s: file already exists</error>', $phpFile));
        }

        $tplFile = $basedir->string('templates', $ctx['namespace'], 'Form', $ctx['formClassName'] . '.tpl');
        if (!file_exists($tplFile)) {
            $output->writeln(sprintf('<info>Write %s</info>', $tplFile));
            file_put_contents($tplFile, $this->getContainer()->get('templating')->render('CRMCivixBundle:Code:form.tpl.php', $ctx));
        } else {
            $output->writeln(sprintf('<error>Skip %s: file already exists</error>', $tplFile));
        }

        $module = new Module($this->getContainer()->get('templating'));
        $module->loadInit($ctx);
        $module->save($ctx, $output);
    }
}
