<?php


namespace test\unit\Ingenerator\PHPUtils\Assets;


use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Ingenerator\PHPUtils\Assets\StaticAssetUrlProvider;

class StaticAssetUrlProviderTest extends TestCase
{

    protected $options = [
        'mode'                => StaticAssetUrlProvider::MODE_LOCAL,
        'local_asset_path'    => __DIR__,
        'asset_base_url_file' => __FILE__,
    ];

    /**
     * @var vfsStreamDirectory
     */
    protected $vfs;

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(StaticAssetUrlProvider::class, $this->newSubject());
    }

    public function test_it_throws_in_invalid_mode()
    {
        $this->options['mode'] = 'some-junk';
        $this->expectException(\InvalidArgumentException::class);
        $this->newSubject();
    }

    public function test_in_local_mode_get_url_throws_if_file_does_not_exist()
    {
        $subject = $this->newSubject();
        $this->expectException(\RuntimeException::class);
        $subject->getUrl('assets/some-file.css');
    }

    /**
     * @testWith ["assets/my-file.css"]
     *           ["/assets/my-file.css"]
     */
    public function test_in_local_mode_get_url_returns_absolute_url_with_mtime_suffix($rel_path)
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

    public function test_in_remote_mode_get_url_throws_if_asset_base_url_file_does_not_exist()
    {
        $this->options['asset_base_url_file'] = $this->vfs->url().'/no-such-file.php';
        $this->options['mode']                = StaticAssetUrlProvider::MODE_REMOTE;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no-such-file.php');
        $this->newSubject();
    }

    /**
     * @testWith [""]
     *           ["some content that is not php"]
     *           ["<?php $a = 1;"]
     *           ["<?php return '';"]
     */
    public function test_in_remote_mode_get_url_throws_if_asset_base_url_file_does_not_return_string($file_content)
    {
        vfsStream::create(
            ['asset-base-url.php' => $file_content],
            $this->vfs
        );

        $this->options['asset_base_url_file'] = $this->vfs->getChild('asset-base-url.php')->url();
        $this->options['mode']                = StaticAssetUrlProvider::MODE_REMOTE;
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid content in asset base url');
        $this->newSubject();
    }

    /**
     * @testWith ["assets/my-file.css"]
     *           ["/assets/my-file.css"]
     */
    public function test_it_remote_mode_get_url_returns_url_prefixed_with_base_url($rel_path)
    {
        vfsStream::create(
            ['asset-base-url.php' => '<?php return "https://i.am.the.walrus/branch/sha";'],
            $this->vfs
        );

        $this->options['asset_base_url_file'] = $this->vfs->getChild('asset-base-url.php')->url();
        $this->options['mode']                = StaticAssetUrlProvider::MODE_REMOTE;

        $this->assertSame(
            'https://i.am.the.walrus/branch/sha/assets/my-file.css',
            $this->newSubject()->getUrl($rel_path)
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->vfs                            = vfsStream::setup('vfs', NULL, ['docroot' => []]);
        $this->options['local_asset_path']    = $this->vfs->getChild('docroot')->url();
        $this->options['asset_base_url_file'] = $this->vfs->url().'/asset-base-url.php';
    }

    protected function newSubject()
    {
        return new StaticAssetUrlProvider(
            $this->options['mode'],
            $this->options['local_asset_path'],
            $this->options['asset_base_url_file']
        );
    }

}
