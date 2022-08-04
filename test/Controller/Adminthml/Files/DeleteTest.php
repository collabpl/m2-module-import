<?php

namespace Jh\ImportTest\Controller\Adminhtml\Files;

use Jh\Import\Config\Data;
use Jh\Import\Controller\Adminhtml\Files\Delete;
use Jh\UnitTestHelpers\ObjectHelper;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Config\CacheInterface;
use Magento\Framework\Config\ReaderInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Filesystem\Directory\WriteFactory;
use Magento\Framework\Filesystem\DriverPool;
use Magento\Framework\Serialize\Serializer\Serialize;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Filesystem\Filesystem;

class DeleteTest extends TestCase
{
    use ObjectHelper;
    use ProphecyTrait;

    /**
     * @var string
     */
    private $tempDirectory;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var WriteFactory
     */
    private $writeFactory;

    /**
     * @var Index
     */
    private $controller;

    public function setUp(): void
    {
        $this->tempDirectory = sprintf('%s/%s/var', realpath(sys_get_temp_dir()), $this->getName());
        @mkdir($this->tempDirectory, 0777, true);

        $this->directoryList = new DirectoryList(dirname($this->tempDirectory));
        $this->writeFactory = new WriteFactory(new DriverPool());

        $context = $this->getObject(Context::class);

        $reader = $this->prophesize(ReaderInterface::class);
        $cache  = $this->prophesize(CacheInterface::class);

        $imports = [
            'product' => ['type' => 'files', 'failed' => 'jh_import/failed'],
            'stock' => ['type' => 'files']
        ];

        $cache->load('cache-id')->willReturn(serialize($imports))->shouldBeCalled();
        $data = new Data($reader->reveal(), $cache->reveal(), 'cache-id', new Serialize());

        $this->controller = new Delete(
            $context,
            $this->directoryList,
            $this->writeFactory,
            $data
        );
    }

    public function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDirectory);
    }

    public function testRedirectIsReturnedIfNameParamNotPreset(): void
    {
        $this->retrieveChildMock(Context::class, 'request')
            ->getParam('name')
            ->willReturn(false);

        $redirect = $this->prophesize(Redirect::class);
        $redirect->setPath('*/*/index')->willReturn($redirect);

        $this->retrieveChildMock(Context::class, 'resultRedirectFactory')
            ->create()
            ->willReturn($redirect->reveal());

        self::assertSame($redirect->reveal(), $this->controller->execute());
    }

    public function testRedirectIsReturnedIfImportDoesNotExist(): void
    {
        $this->retrieveChildMock(Context::class, 'request')
            ->getParam('name')
            ->willReturn('image');

        $redirect = $this->prophesize(Redirect::class);
        $redirect->setPath('*/*/index')->willReturn($redirect);

        $this->retrieveChildMock(Context::class, 'resultRedirectFactory')
            ->create()
            ->willReturn($redirect->reveal());

        self::assertSame($redirect->reveal(), $this->controller->execute());
    }

    public function testFileIsDeletedAndUserRedirected(): void
    {
        $this->retrieveChildMock(Context::class, 'request')
            ->getParam('name')
            ->willReturn('product');

        $this->retrieveChildMock(Context::class, 'request')
            ->getParam('directory')
            ->willReturn('failed');

        $this->retrieveChildMock(Context::class, 'request')
            ->getParam('file')
            ->willReturn('my-file.csv');

        @mkdir($this->tempDirectory . '/jh_import/failed', 0777, true);
        file_put_contents($this->tempDirectory . '/jh_import/failed/my-file.csv', '1,2,3,4');

        $message = sprintf('File "%s/jh_import/failed/my-file.csv" was successfully deleted', $this->tempDirectory);
        $this->retrieveChildMock(Context::class, 'messageManager')
            ->addSuccessMessage($message)
            ->shouldBeCalled();

        $redirect = $this->prophesize(Redirect::class);
        $redirect->setPath('*/config/info', ['name' => 'product'])->willReturn($redirect);

        $this->retrieveChildMock(Context::class, 'resultRedirectFactory')
            ->create()
            ->willReturn($redirect->reveal());

        self::assertSame($redirect->reveal(), $this->controller->execute());
        self::assertFileDoesNotExist($this->tempDirectory . '/jh_import/failed/my-file.csv');
    }
}
