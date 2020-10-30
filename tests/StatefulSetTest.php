<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sPersistentVolumeClaim;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\Kinds\K8sStatefulSet;
use RenokiCo\PhpK8s\ResourcesList;

class StatefulSetTest extends TestCase
{
    public function test_stateful_set_build()
    {
        $mysql = K8s::container()
            ->setName('mysql')
            ->setImage('mysql', '5.7')
            ->setPorts([
                ['name' => 'mysql', 'protocol' => 'TCP', 'containerPort' => 3306],
            ]);

        $pod = $this->cluster->pod()
            ->setName('mysql')
            ->setContainers([$mysql]);

        $svc = $this->cluster->service()
            ->setName('mysql')
            ->setPorts([
                ['protocol' => 'TCP', 'port' => 3306, 'targetPort' => 3306],
            ]);

        $pvc = $this->cluster->persistentVolumeClaim()
            ->setName('mysql-pvc')
            ->setCapacity(1, 'Gi')
            ->setAccessModes(['ReadWriteOnce'])
            ->setStorageClass('gp2');

        $sts = $this->cluster->statefulSet()
            ->setName('mysql')
            ->setLabels(['tier' => 'backend'])
            ->setAnnotations(['mysql/annotation' => 'yes'])
            ->setReplicas(3)
            ->setService($svc)
            ->setTemplate($pod)
            ->setVolumeClaims([$pvc]);

        $this->assertEquals('apps/v1', $sts->getApiVersion());
        $this->assertEquals('mysql', $sts->getName());
        $this->assertEquals(['tier' => 'backend'], $sts->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $sts->getAnnotations());
        $this->assertEquals(3, $sts->getReplicas());
        $this->assertEquals($svc->getName(), $sts->getService());
        $this->assertEquals($pod->getName(), $sts->getTemplate()->getName());
        $this->assertEquals($pvc->getName(), $sts->getVolumeClaims()[0]->getName());

        $this->assertInstanceOf(K8sPod::class, $sts->getTemplate());
        $this->assertInstanceOf(K8sPersistentVolumeClaim::class, $sts->getVolumeClaims()[0]);
    }

    public function test_stateful_set_from_yaml()
    {
        $mysql = K8s::container()
            ->setName('mysql')
            ->setImage('mysql', '5.7')
            ->setPorts([
                ['name' => 'mysql', 'protocol' => 'TCP', 'containerPort' => 3306],
            ]);

        $pod = $this->cluster->pod()
            ->setName('mysql')
            ->setContainers([$mysql]);

        $svc = $this->cluster->service()
            ->setName('mysql')
            ->setPorts([
                ['protocol' => 'TCP', 'port' => 3306, 'targetPort' => 3306],
            ]);

        $pvc = $this->cluster->persistentVolumeClaim()
            ->setName('mysql-pvc')
            ->setCapacity(1, 'Gi')
            ->setAccessModes(['ReadWriteOnce'])
            ->setStorageClass('gp2');

        $sts = $this->cluster->fromYamlFile(__DIR__.'/yaml/statefulset.yaml');

        $this->assertEquals('apps/v1', $sts->getApiVersion());
        $this->assertEquals('mysql', $sts->getName());
        $this->assertEquals(['tier' => 'backend'], $sts->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $sts->getAnnotations());
        $this->assertEquals(3, $sts->getReplicas());
        $this->assertEquals($svc->getName(), $sts->getService());
        $this->assertEquals($pod->getName(), $sts->getTemplate()->getName());
        $this->assertEquals($pvc->getName(), $sts->getVolumeClaims()[0]->getName());

        $this->assertInstanceOf(K8sPod::class, $sts->getTemplate());
        $this->assertInstanceOf(K8sPersistentVolumeClaim::class, $sts->getVolumeClaims()[0]);
    }

    public function test_stateful_set_api_interaction()
    {
        $this->runCreationTests();
        $this->runGetAllTests();
        $this->runGetTests();
        $this->runUpdateTests();
        $this->runWatchAllTests();
        $this->runWatchTests();
        $this->runDeletionTests();
    }

    public function runCreationTests()
    {
        $mysql = K8s::container()
            ->setName('mysql')
            ->setImage('mysql', '5.7')
            ->setPorts([
                ['name' => 'mysql', 'protocol' => 'TCP', 'containerPort' => 3306],
            ])
            ->addPort(3307, 'TCP', 'mysql-alt')
            ->setEnv([[
                'name' => 'MYSQL_ROOT_PASSWORD',
                'value' => 'test',
            ]]);

        $pod = $this->cluster->pod()
            ->setName('mysql')
            ->setLabels(['tier' => 'backend', 'statefulset-name' => 'mysql'])
            ->setAnnotations(['mysql/annotation' => 'yes'])
            ->setContainers([$mysql]);

        $svc = $this->cluster->service()
            ->setName('mysql')
            ->setPorts([
                ['protocol' => 'TCP', 'port' => 3306, 'targetPort' => 3306],
            ])
            ->syncWithCluster();

        $pvc = $this->cluster->persistentVolumeClaim()
            ->setName('mysql-pvc')
            ->setCapacity(1, 'Gi')
            ->setAccessModes(['ReadWriteOnce'])
            ->setStorageClass('gp2');

        $sts = $this->cluster->statefulSet()
            ->setName('mysql')
            ->setLabels(['tier' => 'backend'])
            ->setAnnotations(['mysql/annotation' => 'yes'])
            ->setSelectors(['matchLabels' => ['tier' => 'backend']])
            ->setReplicas(1)
            ->setService($svc)
            ->setTemplate($pod)
            ->setVolumeClaims([$pvc]);

        $this->assertFalse($sts->isSynced());
        $this->assertFalse($sts->exists());

        $sts = $sts->syncWithCluster();

        $this->assertTrue($sts->isSynced());
        $this->assertTrue($sts->exists());

        $this->assertInstanceOf(K8sStatefulSet::class, $sts);

        $this->assertEquals('apps/v1', $sts->getApiVersion());
        $this->assertEquals('mysql', $sts->getName());
        $this->assertEquals(['tier' => 'backend'], $sts->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $sts->getAnnotations());
        $this->assertEquals(1, $sts->getReplicas());
        $this->assertEquals($svc->getName(), $sts->getService());
        $this->assertEquals($pod->getName(), $sts->getTemplate()->getName());
        $this->assertEquals($pvc->getName(), $sts->getVolumeClaims()[0]->getName());

        $this->assertInstanceOf(K8sPod::class, $sts->getTemplate());
        $this->assertInstanceOf(K8sPersistentVolumeClaim::class, $sts->getVolumeClaims()[0]);

        $pods = $sts->getPods();

        $this->assertTrue($pods->count() > 0);

        foreach ($pods as $pod) {
            $this->assertInstanceOf(K8sPod::class, $pod);
        }

        // Wait for the pod to create entirely.
        sleep(60);
    }

    public function runGetAllTests()
    {
        $statefulsets = $this->cluster->getAllStatefulSets();

        $this->assertInstanceOf(ResourcesList::class, $statefulsets);

        foreach ($statefulsets as $sts) {
            $this->assertInstanceOf(K8sStatefulSet::class, $sts);

            $this->assertNotNull($sts->getName());
        }
    }

    public function runGetTests()
    {
        $sts = $this->cluster->getStatefulSetByName('mysql');

        $this->assertInstanceOf(K8sStatefulSet::class, $sts);

        $this->assertTrue($sts->isSynced());

        $this->assertEquals('apps/v1', $sts->getApiVersion());
        $this->assertEquals('mysql', $sts->getName());
        $this->assertEquals(['tier' => 'backend'], $sts->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $sts->getAnnotations());
        $this->assertEquals(1, $sts->getReplicas());

        $this->assertInstanceOf(K8sPod::class, $sts->getTemplate());
        $this->assertInstanceOf(K8sPersistentVolumeClaim::class, $sts->getVolumeClaims()[0]);
    }

    public function runUpdateTests()
    {
        $sts = $this->cluster->getStatefulSetByName('mysql');

        $this->assertTrue($sts->isSynced());

        $sts->setAnnotations([]);

        $this->assertTrue($sts->update());

        $this->assertTrue($sts->isSynced());

        $this->assertEquals('apps/v1', $sts->getApiVersion());
        $this->assertEquals('mysql', $sts->getName());
        $this->assertEquals(['tier' => 'backend'], $sts->getLabels());
        $this->assertEquals([], $sts->getAnnotations());
        $this->assertEquals(1, $sts->getReplicas());

        $this->assertInstanceOf(K8sPod::class, $sts->getTemplate());
        $this->assertInstanceOf(K8sPersistentVolumeClaim::class, $sts->getVolumeClaims()[0]);
    }

    public function runDeletionTests()
    {
        $sts = $this->cluster->getStatefulSetByName('mysql');

        $this->assertTrue($sts->delete());

        sleep(60);

        $this->expectException(KubernetesAPIException::class);

        $pod = $this->cluster->getStatefulSetByName('mysql');
    }

    public function runWatchAllTests()
    {
        $watch = $this->cluster->statefulSet()->watchAll(function ($type, $sts) {
            if ($sts->getName() === 'mysql') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests()
    {
        $watch = $this->cluster->statefulSet()->watchByName('mysql', function ($type, $sts) {
            return $sts->getName() === 'mysql';
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
