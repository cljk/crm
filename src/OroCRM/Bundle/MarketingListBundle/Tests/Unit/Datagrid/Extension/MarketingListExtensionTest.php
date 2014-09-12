<?php

namespace OroCRM\Bundle\MarketingListBundle\Tests\Unit\Datagrid\Extension;

use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Func;
use Oro\Bundle\DataGridBundle\Datagrid\Builder;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\SegmentBundle\Entity\Segment;
use OroCRM\Bundle\MarketingListBundle\Datagrid\Extension\MarketingListExtension;
use OroCRM\Bundle\MarketingListBundle\Model\MarketingListSegmentHelper;

class MarketingListExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MarketingListExtension
     */
    protected $extension;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $registry;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $repository;

    protected function setUp()
    {
        $this->registry = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');

        $om = $this
            ->getMockBuilder('Doctrine\Common\Persistence\ObjectManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->repository = $this
            ->getMockBuilder('\Doctrine\Common\Persistence\ObjectRepository')
            ->disableOriginalConstructor()
            ->getMock();

        $om
            ->expects($this->any())
            ->method('getRepository')
            ->with($this->equalTo(MarketingListSegmentHelper::MARKETING_LIST))
            ->will($this->returnValue($this->repository));

        $this->registry
            ->expects($this->any())
            ->method('getManagerForClass')
            ->with($this->equalTo(MarketingListSegmentHelper::MARKETING_LIST))
            ->will($this->returnValue($om));

        $this->extension = new MarketingListExtension(
            new MarketingListSegmentHelper($this->registry)
        );
    }

    /**
     * @param string      $gridName
     * @param string      $dataSource
     * @param bool        $isMixin
     * @param object|null $entity
     * @param bool        $expected
     *
     * @dataProvider applicableDataProvider
     */
    public function testIsApplicable($gridName, $dataSource, $isMixin, $entity, $expected)
    {
        $config = $this
            ->getMockBuilder('Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration')
            ->disableOriginalConstructor()
            ->getMock();

        $config
            ->expects($this->any())
            ->method('offsetGetByPath')
            ->will(
                $this->returnValueMap(
                    [
                        ['[name]', null, $gridName],
                        [Builder::DATASOURCE_TYPE_PATH, null, $dataSource],
                        [MarketingListExtension::OPTIONS_MIXIN_PATH, false, $isMixin]
                    ]
                )
            );

        $this->repository
            ->expects($this->any())
            ->method('findOneBy')
            ->will($this->returnValue($entity));

        $this->assertEquals(
            $expected,
            $this->extension->isApplicable($config)
        );
    }

    /**
     * @return array
     */
    public function applicableDataProvider()
    {
        return [
            ['gridName', 'dataSource', false, null, false],
            ['gridName', 'dataSource', true, null, false],
            ['gridName', OrmDatasource::TYPE, false, null, false],
            ['gridName', OrmDatasource::TYPE, true, null, false],
            [Segment::GRID_PREFIX, OrmDatasource::TYPE, false, null, false],
            [Segment::GRID_PREFIX, OrmDatasource::TYPE, true, null, false],
            [Segment::GRID_PREFIX . '1', OrmDatasource::TYPE, false, new \stdClass(), false],
            [Segment::GRID_PREFIX . '1', OrmDatasource::TYPE, true, new \stdClass(), true],
        ];
    }

    /**
     * @param array $dqlParts
     * @param bool  $isMixin
     * @param bool  $expected
     *
     * @dataProvider dataSourceDataProvider
     */
    public function testVisitDatasource($dqlParts, $isMixin, $expected)
    {
        $config = $this
            ->getMockBuilder('Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration')
            ->disableOriginalConstructor()
            ->getMock();

        $this->repository
            ->expects($this->any())
            ->method('findOneBy')
            ->will($this->returnValue(new \stdClass()));

        $id = '1';

        $config
            ->expects($this->any())
            ->method('offsetGetByPath')
            ->will(
                $this->returnValueMap(
                    [
                        ['[source][type]', null, OrmDatasource::TYPE],
                        ['[name]', null, Segment::GRID_PREFIX . $id],
                        [MarketingListExtension::OPTIONS_MIXIN_PATH, false, $isMixin]
                    ]
                )
            );

        $dataSource = $this
            ->getMockBuilder('Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource')
            ->disableOriginalConstructor()
            ->getMock();

        $qb = $this->getQbMock();

        if (!empty($dqlParts['where'])) {
            /** @var Andx $where */
            $where = $dqlParts['where'];
            $parts = $where->getParts();

            if ($expected) {
                $qb
                    ->expects($this->exactly(sizeof($parts)))
                    ->method('andWhere');
            }

            $functionParts = array_filter(
                $parts,
                function ($part) {
                    return !is_string($part);
                }
            );

            if ($functionParts && $expected) {
                $qb
                    ->expects($this->once())
                    ->method('setParameter')
                    ->with($this->equalTo('segmentId'), $this->equalTo($id));
            }
        }

        if ($expected) {
            $qb
                ->expects($this->once())
                ->method('getDQLParts')
                ->will($this->returnValue($dqlParts));

            $dataSource
                ->expects($this->once())
                ->method('getQueryBuilder')
                ->will($this->returnValue($qb));
        }

        $this->extension->visitDatasource($config, $dataSource);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getQbMock()
    {
        $qb = $this
            ->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $qb
            ->expects($this->any())
            ->method('from')
            ->will($this->returnSelf());

        $qb
            ->expects($this->any())
            ->method('leftJoin')
            ->will($this->returnSelf());

        $qb
            ->expects($this->any())
            ->method('select')
            ->will($this->returnSelf());

        $expr = $this
            ->getMockBuilder('Doctrine\ORM\Query\Expr')
            ->disableOriginalConstructor()
            ->getMock();

        $qb
            ->expects($this->any())
            ->method('expr')
            ->will($this->returnValue($expr));

        $orX = $this
            ->getMockBuilder('Doctrine\ORM\Query\Expr')
            ->disableOriginalConstructor()
            ->getMock();

        $expr
            ->expects($this->any())
            ->method('orX')
            ->will($this->returnValue($orX));

        return $qb;
    }

    /**
     * @return array
     */
    public function dataSourceDataProvider()
    {
        return [
            [['where' => []], false, false],
            [['where' => []], true, true],
            [['where' => new Andx()], false, false],
            [['where' => new Andx()], true, true],
            [['where' => new Andx(['test'])], false, false],
            [['where' => new Andx(['test'])], true, true],
            [['where' => new Andx([new Func('func condition', ['argument'])])], false, false],
            [['where' => new Andx([new Func('func condition', ['argument'])])], true, true],
            [['where' => new Andx(['test', new Func('func condition', ['argument'])])], false, false],
            [['where' => new Andx(['test', new Func('func condition', ['argument'])])], true, true],
        ];
    }

    public function testGetPriority()
    {
        $this->assertInternalType('integer', $this->extension->getPriority());
    }
}