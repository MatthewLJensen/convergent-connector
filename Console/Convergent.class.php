<?php
namespace FreePBX\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

require_once dirname(__DIR__) . '/Signing.php';

use FreePBX\modules\Convergentdashboard\Signing;

/**
 * fwconsole convergent trust-key [--status|--remove]
 *
 * Trust the release key this module is signed with, so FreePBX shows it
 * (and anything else signed with it, e.g. OpenProv) as signed instead of
 * "unsigned". See SIGNING.md.
 */
class Convergent extends Command
{
	protected function configure()
	{
		$this->setName('convergent')
			->setDescription('Convergent Dashboard module')
			->addArgument('action', InputArgument::REQUIRED, 'trust-key')
			->addOption('status', null, InputOption::VALUE_NONE, 'Only show whether the release key is trusted')
			->addOption('remove', null, InputOption::VALUE_NONE, 'Stop trusting the release key (root)');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if ($input->getArgument('action') !== 'trust-key') {
			$output->writeln('<error>Unknown action. Usage: fwconsole convergent trust-key [--status|--remove]</error>');
			return 2;
		}
		$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
		$signing = new Signing();
		try {
			if ($input->getOption('status')) {
				$output->writeln(json_encode($signing->status(), $flags));
				return 0;
			}
			if (posix_geteuid() !== 0) {
				$output->writeln('<error>trust-key changes the system keyring: run it as root</error>');
				return 1;
			}
			$r = $input->getOption('remove') ? $signing->untrust() : $signing->trust();
			$r['modules'] = Signing::afterChange();
			$output->writeln(json_encode($r, $flags));
			return 0;
		} catch (\Throwable $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return 1;
		}
	}
}
