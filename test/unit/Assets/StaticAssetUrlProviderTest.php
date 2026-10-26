<?php

namespace test\unit\Ingenerator\PHPUtils\Assets;

use Ingenerator\PHPUtils\Assets\StaticAssetUrlProvider;
use InvalidArgumentException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StaticAssetUrlProviderTest extends TestCase
{
    protected $options = [
        'mode' => StaticAssetUrlProvider::MODE_LOCAL,
        'local_asset_path' => __DIR__,
        'asset_base_url_file' => __FILE__,
    ];

    protected vfsStreamDirectory $vfs;

    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(StaticAssetUrlProvider::class, $this->newSubject());
    }

    public function testItThrowsInInvalidMode(): void
    {
        $this->options['mode'] = 'some-junk';
        $this->expectException(InvalidArgumentException::class);
        $this->newSubject();
    }

    public function testInLocalModeGetUrlThrowsIfFileDoesNotExist(): void
    {
        $subject = $this->newSubject();
        $this->expectException(RuntimeException::class);
        $subject->getUrl('assets/some-file.css');
    }

    #[TestWith(['assets/my-file.css'])]
    #[TestWith(['/assets/my-file.css'])]
    public function testInLocalModeGetUrlReturnsAbsoluteUrlWithMtimeSuffix($rel_path): void
    {
        vfsStream::create(
            [
                'assets' => [
                    'my-file.css' => 'some-content',
                ],
            ],
            $this->vfs->getChild('docroot')
        );
        $this->vfs->getChild('docroot/assets/my-file.css')->lastModified(123456);
        $this->assertSame(
            '/assets/my-file.css?v=123456',
            $this->newSubject()->getUrl($rel_path)
        );
    }

    public function testInRemoteModeGetUrlThrowsIfAssetBaseUrlFileDoesNotExist(): void
    {
        $this->options['asset_base_url_file'] = $this->vfs->url().'/no-such-file.php';
        $this->options['mode'] = StaticAssetUrlProvider::MODE_REMOTE;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no-such-file.php');
        $this->newSubject();
    }

    #[TestWith([''])]
    #[TestWith(['some content that is not php'])]
    #[TestWith(['<?php $a = 1;'])]
    #[TestWith(["<?php return '';"])]
    public function testInRemoteModeGetUrlThrowsIfAssetBaseUrlFileDoesNotReturnString($file_content): void
    {
        vfsStream::create(
            ['asset-base-url.php' => $file_content],
            $this->vfs
        );

        $this->options['asset_base_url_file'] = $this->vfs->getChild('asset-base-url.php')->url();
        $this->options['mode'] = StaticAssetUrlProvider::MODE_REMOTE;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid content in asset base url');
        $this->newSubject();
    }

    #[TestWith(['assets/my-file.css'])]
    #[TestWith(['/assets/my-file.css'])]
    public function testItRemoteModeGetUrlReturnsUrlPrefixedWithBaseUrl($rel_path): void
    {
        vfsStream::create(
            ['asset-base-url.php' => '<?php return "https://i.am.the.walrus/branch/sha";'],
            $this->vfs
        );

        $this->options['asset_base_url_file'] = $this->vfs->getChild('asset-base-url.php')->url();
        $this->options['mode'] = StaticAssetUrlProvider::MODE_REMOTE;

        $this->assertSame(
            'https://i.am.the.walrus/branch/sha/assets/my-file.css',
            $this->newSubject()->getUrl($rel_path)
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->vfs = vfsStream::setup('vfs', null, ['docroot' => []]);
        $this->options['local_asset_path'] = $this->vfs->getChild('docroot')->url();
        $this->options['asset_base_url_file'] = $this->vfs->url().'/asset-base-url.php';
    }

    protected function newSubject(): StaticAssetUrlProvider
    {
        return new StaticAssetUrlProvider(
            $this->options['mode'],
            $this->options['local_asset_path'],
            $this->options['asset_base_url_file']
        );
    }
}
