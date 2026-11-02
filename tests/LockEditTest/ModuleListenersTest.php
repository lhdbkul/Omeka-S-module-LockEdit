<?php declare(strict_types=1);

namespace LockEditTest;

use CommonTest\AbstractHttpControllerTestCase;
use Laminas\EventManager\Event;
use Laminas\View\Renderer\PhpRenderer;
use LockEdit\Module;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Request;

/**
 * Tests of the listeners of the module called directly.
 */
class ModuleListenersTest extends AbstractHttpControllerTestCase
{
    use LockEditTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->enableContentLock();
        $this->cleanContentLocks();
    }

    public function tearDown(): void
    {
        $this->cleanContentLocks();
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * A dry run (option "validateOnly", used by module Contribute) does not
     * persist the resource, so the content lock is kept.
     */
    public function testValidateOnlyKeepsContentLock(): void
    {
        $item = $this->createItem();
        $this->createContentLock($item->id(), 'items');

        $request = new Request(Request::UPDATE, 'items');
        $request->setId($item->id());
        $request->setOption('validateOnly', true);
        $this->module()->contentLockingOnSave(new Event('api.update.pre', null, ['request' => $request]));
        $this->getEntityManager()->flush();

        $this->assertSame(1, $this->countContentLocks());
    }

    /**
     * Without the option "validateOnly", the same request removes the lock of
     * the current user.
     */
    public function testSaveRemovesContentLock(): void
    {
        $item = $this->createItem();
        $this->createContentLock($item->id(), 'items');

        $request = new Request(Request::UPDATE, 'items');
        $request->setId($item->id());
        $this->module()->contentLockingOnSave(new Event('api.update.pre', null, ['request' => $request]));
        $this->getEntityManager()->flush();

        $this->assertSame(0, $this->countContentLocks());
    }

    /**
     * The name of a resource that is not mapped, for example a resource type
     * added by a module, is read from the representation, not from its json-ld
     * type, that may be an array.
     */
    public function testEditLocksResourceNotMappedViaItsApiName(): void
    {
        $item = $this->createItem();
        $services = $this->getServiceLocator();
        $entity = $this->getEntityManager()->find(\Omeka\Entity\Item::class, $item->id());
        $adapter = $services->get('Omeka\ApiAdapterManager')->get('items');
        $representation = new class($entity, $adapter) extends ItemRepresentation {
        };

        $view = new PhpRenderer();
        $view->resource = $representation;
        $this->module()->contentLockingOnEdit(new Event('view.edit.before', $view));

        $this->assertNotNull($this->getContentLock($item->id(), 'items'));
    }

    protected function module(): Module
    {
        $module = new Module();
        $module->setServiceLocator($this->getServiceLocator());
        return $module;
    }
}
