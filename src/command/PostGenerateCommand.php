<?php
namespace clomery\command;

use Spyc;
use suda\core\storage\FileStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PostGenerateCommand extends Command
{
    protected static $defaultName = 'post:generate';

    protected function configure()
    {
        $this
        ->setDescription('generate the markdown post data')
        ->setHelp('you can use this command to generate markdown post data')
        ->addArgument('path', InputArgument::REQUIRED, 'the path to markdown')
        ->addOption('force', 'f', InputOption::VALUE_NONE, 'force update post data')
        ->addOption('database', 'db', InputOption::VALUE_OPTIONAL, 'the path to save scan database', './clomery-data')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $storage = new FileStorage;
        $io = new SymfonyStyle($input, $output);
        $inputPath = $input->getArgument('path');
        $outputPath = $input->getOption('database');
        $force = $input->getOption('force');
        $inputPath = $storage->abspath($inputPath);
        $outputPath = $storage->path($outputPath);
        $database = [];
        $io->title('Markdown Post Data Generater');
        $io->text('read meta data from markdown <info>'.$inputPath.'</>');
        $databasePath = $outputPath.'/posts.json';
        $sortPath = pathinfo($inputPath, PATHINFO_BASENAME);
        $name = pathinfo($inputPath, PATHINFO_FILENAME);
        $hash = \md5_file($inputPath);
        $notUpdate=false;
        if ($storage->exist($databasePath)) {
            $database = \json_decode($storage->get($databasePath), true);
            if (array_key_exists($sortPath, $database)) {
                if ($database[$sortPath][1] === $hash) {
                    $notUpdate=true;
                } else {
                    $database[$sortPath]=[$name, $hash, $sortPath, $inputPath];
                    $io->text('save to database <info>'.$databasePath.'</>');
                }
            } else {
                $database [$sortPath ]= [
                    $name, $hash, $sortPath, $inputPath
                ];
                $storage->put($databasePath, \json_encode($database, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $io->text('save to database <info>'.$databasePath.'</>');
            }
        } else {
            $database =[
                $sortPath => [
                    $name, $hash, $sortPath, $inputPath
                ]
            ];
            $storage->put($databasePath, \json_encode($database, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $io->text('save to database <info>'.$databasePath.'</>');
        }
        $outPostPath= $storage->path($outputPath.'/posts/');
        $articlePath = $outPostPath .'/' .$name.'.json';
        if ($storage->exist($articlePath)) {
            if ($force) {
                $notUpdate = false;
                $articleData = \json_decode($storage->get($articlePath), true);
                $data = $this->readArticle($inputPath, $io, $storage);
                if ($data !== null) {
                    $data['meta']['modify'] = date('Y-m-d H:i:s');
                    $storage->put($articlePath, \json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $io->text('article data saved: <info>'.$articlePath.'</>');
                }
            } else {
                $notUpdate = true;
            }
        } else {
            $notUpdate = false;
            $data = $this->readArticle($inputPath, $io, $storage);
            if ($data !== null) {
                $storage->put($articlePath, \json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $io->text('article data saved: <info>'.$articlePath.'</>');
            }
        }
        if ($notUpdate) {
            $io->text('post date is not update');
        }
    }

    protected function readArticle(string $inputPath, SymfonyStyle $io, FileStorage $storage)
    {
        $article = $storage->get($inputPath);
        if (preg_match('/^\-\-\-\r?\n(.+)\-\-\-\r?\n(?:(.+)\<\!\-\-\s+more\s+\-\-\>)?(.+)$/ims', $article, $match)) {
            list($raw, $meta, $excerpt, $content) = $match;
            $meta = Spyc::YAMLLoadString($meta);
            $excerpt = trim($excerpt);
            $content = trim($content);
            $meta['create'] = $meta['date'];
            $data = [
                'meta' => $meta,
                'excerpt' => $excerpt,
                'content' => $content,
            ];
            $io->section('article data');
            $io->listing([
                'title: '. $meta['title'],
                'excerpt: ' .substr($excerpt, 0, 100),
            ]);
            return $data;
        } else {
            $io->error('error markdown format');
            return null;
        }
    }
}
