<?php
namespace clomery\command;

use clomery\markdown\LinkParse;
use suda\core\storage\FileStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PostAnalysisCommand extends Command
{
    protected static $defaultName = 'post:analysis';

    protected function configure()
    {
        $this
        ->setDescription('analysis the markdown archive, image and reference posts')
        ->addArgument('name', InputArgument::REQUIRED, 'article name')
        ->addOption('database', 'db', InputOption::VALUE_OPTIONAL, 'the path to save scan database', './clomery-data');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $s = new FileStorage;
        $name = $input->getArgument('name');
        $outputPath = $input->getOption('database');
        $outputPath = $s->path($outputPath);
        $data = $s->get($outputPath.'/posts.json');
        $dataPost = $s->get($outputPath.'/posts/'.$name.'.json');
        if (strlen($data) <= 0 || strlen($dataPost) <= 0) {
            return false;
        }
        $data = json_decode($data, true);
        $dataPost = json_decode($dataPost, true);
        if (!array_key_exists($name.'.md', $data)) {
            return false;
        }
        $rootPath =  dirname($data[$name.'.md'][3]);
        $linkParse = LinkParse::instance();
        $linkParse ->text($dataPost['content']);
        
        $io->section('article images');
        $io->listing($linkParse->getImages());
        $io->section('article attachments');
        $io->listing($linkParse->getAttachments());
        $s->path($outputPath.'/posts/'.$name);
        

        $imageJson =  $outputPath.'/posts/'.$name.'/image.json';
        $attachmentJson =  $outputPath.'/posts/'.$name.'/attachment.json';

        $s->put($imageJson, \json_encode($linkParse->getImages(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $s->put($attachmentJson, \json_encode($linkParse->getAttachments(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        foreach ($linkParse->getImages() as $imagePath) {
            $s->copy($rootPath.'/'.$imagePath, $outputPath.'/posts/'.$name.'/resource/' .$imagePath);
        }

        foreach ($linkParse->getAttachments() as $attachment) {
            $s->copy($rootPath.'/'.$attachment, $outputPath.'/posts/'.$name.'/resource/' .$attachment);
        }
    }
}
