<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace MJanssen\DoctrineModule\Stdlib\Hydrator\Strategy;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Zend\Stdlib\Hydrator\Strategy\StrategyInterface;

/**
 * Implements a strategy that recursively extracts any other entities
 * in collections.
 *
 * @license MIT
 * @link    http://www.doctrine-project.org/
 * @author  Liam O'Boyle <liam@ontheroad.net.nz>
 */
class ExtractRecursiveByValue extends AbstractExtractObjectStrategy
{
    /**
     * Stores a hash of each object seen to be used for loop detection.
     *
     * @var array
     */
    protected $entitiesSeen = [];

    /**
     * A strategy to apply after the first level of recursion, as a
     * way to sensibly limit recursion for common cases (e.g. by
     * specifying ExtractId as a fallback strategy, any further
     * recursion is halted and only ids are returned).
     *
     * @var StrategyInterface
     */
    protected $fallback;

    /**
     * The class of hydrator to use for recursively hydrating.
     *
     * @var string
     */
    protected $hydratorClass = 'DoctrineModule\Stdlib\Hydrator\DoctrineObject';

    /**
     * {@inheritdoc}
     */
    protected function getIdentifier($object, $metadata)
    {
        return $this->getIdentifierByValue($object, $metadata);
    }

    /**
     * Get the fallback strategy for use after the first recursion.
     *
     * @return StrategyInterface
     */
    public function getFallback()
    {
        return $this->fallback ?: $this;
    }

    /**
     * Set the fallback strategy.
     *
     * @param StrategyInterface $fallback
     *
     * @return ExtractRecursiveByValue $this
     */
    public function setFallback(StrategyInterface $fallback)
    {
        $this->fallback = $fallback;

        return $this;
    }

    /**
     * Get the hydrator class.
     *
     * @return string
     */
    public function getHydratorClass()
    {
        return $this->hydratorClass;
    }

    /**
     * Set the hydrator class.
     *
     * @param string $class
     *
     * @return ExtractRecursiveByValue $this
     */
    public function setHydratorClass($class)
    {
        $this->hydratorClass = $class;

        return $this;
    }

    /**
     * Get a hydrator for the current level.
     *
     * @param mixed $object
     *
     * @return Hydrator
     */
    protected function getHydrator($object)
    {
        $class = $this->getHydratorClass();
        return new $class($this->objectManager, get_class($object));
    }

    /**
     * Check if an object has been seen before, to avoid loops.
     *
     * @param mixed $object
     *
     * @return boolean
     */
    protected function hasSeen($object)
    {
        return in_array(spl_object_hash($object), $this->entitiesSeen);
    }

    /**
     * Record an object sighting, to avoid loops.
     *
     * @param mixed $object
     *
     * @return ExtractRecursiveByValue $this
     */
    protected function saw($object)
    {
        $this->entitiesSeen[] = spl_object_hash($object);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function extractObject($object)
    {
        if ($this->hasSeen($object)) {
            $metadata = $this->getMetadata($object);
            return $this->getIdentifier($object, $metadata);
        } else {
            $this->saw($object);

            return $this->getHydrator($object)->extract($object);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function extractCollection(Collection $collection)
    {
        if ($collection->isEmpty()) {
            return [];
        }

        $results     = [];
        $object      = $collection->first();
        $hydrator    = $this->getHydrator($object);
        $metadata    = $this->getMetadata($object);

        foreach ($collection as $object) {
            if ($this->hasSeen($object)) {
                $results[] = $this->getIdentifier($object, $metadata);
            } else {
                $this->saw($object);
                $results[] = $hydrator->extract($object);
            }
        }

        if($this->hasAssociations($metadata)) {
            return $this->extractAssociations($results);
        }

        return $results;
    }

    /**
     * Check if the Entity has associations
     *
     * @param ClassMetadata $metadata
     * @return bool
     */
    protected function hasAssociations(ClassMetadata $metadata)
    {
        if(count($metadata->getAssociationNames()) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @param $results
     * @return mixed
     */
    protected function extractAssociations($results)
    {
        foreach ($results as &$result) {
            foreach ($result as $fieldName => $mixedValue) {
                if($mixedValue instanceof Collection) {
                    $result[$fieldName] = $this->extractCollection($mixedValue);
                }
            }
        }

        return $results;
    }
}