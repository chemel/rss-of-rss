<?php

namespace App\Command;

use App\Service\FeedsFileService;
use App\Service\XmlParser;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:fetch',
    description: 'Récupère les flux RSS référencés par une page « RSS of RSS ».',
)]
class FetchCommand
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly XmlParser $xmlParser,
        private readonly FeedsFileService $feedsFileService,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Url')] string $url,
        #[Option('Fichier dans lequel sauvegarder les URLs des flux trouvés (une par ligne)', shortcut: 'o')] ?string $output = null,
        #[Option('Complète le fichier --output existant, sans doublons, au lieu de l\'écraser', shortcut: 'a')] bool $append = false,
    ): int {
        if ($append && null === $output) {
            $io->error('The --append option requires the --output option.');

            return Command::INVALID;
        }

        $io->note(sprintf('Getting rss of rss from: %s', $url));

        $content = $this->httpClient->request('GET', $url)->getContent();
        $links = $this->xmlParser->parseXmlLinks($content);

        $io->success(sprintf('Found %d links', count($links)));

        // Responses are lazy: all requests are sent concurrently, then read in order
        $responses = [];
        foreach ($links as $link) {
            try {
                $responses[$link] = $this->httpClient->request('GET', $link);
            } catch (TransportExceptionInterface $e) {
                // Thrown synchronously for invalid URLs/options
                $io->writeln(sprintf('<error>[ERR]</error> %s (%s)', $link, $e->getMessage()));
            }
        }

        $feedUrls = [];

        foreach ($responses as $link => $response) {
            try {
                $statusCode = $response->getStatusCode();
                $html = $response->getContent(false);
            } catch (TransportExceptionInterface $e) {
                $io->writeln(sprintf('<error>[ERR]</error> %s (%s)', $link, $e->getMessage()));
                continue;
            }

            if (200 !== $statusCode) {
                $io->writeln(sprintf('<error>[%d]</error> %s', $statusCode, $link));
                continue;
            }

            try {
                $feeds = $this->xmlParser->parseHtmlFeedsUrls($html, $link);
            } catch (\Exception $e) {
                $io->writeln(sprintf('<error>[ERR]</error> %s (%s)', $link, $e->getMessage()));
                continue;
            }

            $io->writeln('<info>[200]</info> '.$link);

            foreach ($feeds as $feed) {
                $io->writeln("\t-> ".$feed);
                $feedUrls[] = $feed;
            }
        }

        if (null !== $output) {
            try {
                $saved = $this->feedsFileService->save($output, $feedUrls, $append);
            } catch (IOException $e) {
                $io->error(sprintf('Unable to save feed urls to "%s": %s', $output, $e->getMessage()));

                return Command::FAILURE;
            }

            if ($append) {
                $io->note(sprintf('Added %d new feed urls to: %s (%d in total)', count($saved->newUrls), $output, count($saved->allUrls)));
            } else {
                $io->note(sprintf('Saved %d feed urls to: %s', count($saved->allUrls), $output));
            }
        }

        $io->success('All done.');

        return Command::SUCCESS;
    }
}
