<?php declare(strict_types=1);

namespace Donut;

/**
 * This is a port of the donut ascii animation algorithm https://www.a1k0n.net/2011/07/20/donut-math.html
 *
 * Class Donut
 * @package Donut
 */
class Donut
{
    /**
     * @var int
     */
    private $screenHeight;

    /**
     * @var int
     */
    private $screenWidth;

    /**
     * @var float
     */
    private $A;

    /**
     * @var float
     */
    private $B;

    /**
     * @var float
     */
    private $thetaSpacing;

    /**
     * @var float
     */
    private $phiSpacing;

    /**
     * @var int
     */
    private $R1;

    /**
     * @var int
     */
    private $R2;

    /**
     * @var float
     */
    private $K1;

    /**
     * @var int
     */
    private $K2;

    /**
     * @var array
     */
    private $outputBuffer;

    public function __construct(int $screenWidth = 20)
    {
        $this->screenWidth = $screenWidth;
        $this->screenHeight = $screenWidth;

        $this->A = 1;
        $this->B = 1;

        $this->thetaSpacing = 0.007;
        $this->phiSpacing = 0.02;

        $this->R1 = 1;
        $this->R2 = 2;

        $this->K2 = 15;
        $this->K1 = $this->screenWidth * $this->K2 * 3 / (8 * ($this->R1 + $this->R2));
    }

    public function showAnimation()
    {
        while (true) {
            $this->outputBuffer = [];
            $this->calculate();
            $this->output();
            usleep(10 * 1000);
        }
    }

    private function calculate()
    {
        $this->A += 0.14;
        $this->B += 0.06;


        $cosA = cos($this->A);
        $sinA = sin($this->A);

        $cosB = cos($this->B);
        $sinB = sin($this->B);

        $output = [];
        $zbuffer = [];

        for ($theta = 0; $theta < 2 * 3.14; $theta += $this->thetaSpacing) {
            // precompute sines and cosines of theta
            $costheta = cos($theta);
            $sintheta = sin($theta);

            // phi goes around the center of revolution of a torus
            for ($phi = 0; $phi < 3 * 3.14; $phi += $this->phiSpacing) {
                // precompute sines and cosines of phi
                $cosphi = cos($phi);
                $sinphi = sin($phi);

                // the x,y coordinate of the circle, before revolving (factored
                // out of the above equations)
                $circlex = $this->R2 + $this->R1 * $costheta;
                $circley = $this->R1 * $sintheta;

                // final 3D (x,y,z) coordinate after rotations, directly from
                // our math above
                $x = $circlex * ($cosB * $cosphi + $sinA * $sinB * $sinphi)
                    - $circley * $cosA * $sinB;
                $y = $circlex * ($sinB * $cosphi - $sinA * $cosB * $sinphi)
                    + $circley * $cosA * $cosB;
                $z = $this->K2 + $cosA * $circlex * $sinphi + $circley * $sinA;
                $ooz = 1 / $z;  // "one over z"

                // x and y projection.  note that y is negated here, because y
                // goes up in 3D space but down on 2D displays.
                $xp = (int) ($this->screenWidth / 2 + $this->K1 * $ooz * $x);
                $yp = (int) ($this->screenHeight / 2 - $this->K1 * $ooz * $y);

                // calculate luminance.  ugly, but correct.
                $L = $cosphi * $costheta * $sinB - $cosA * $costheta * $sinphi -
                    $sinA * $sintheta + $cosB * ($cosA * $sintheta - $costheta * $sinA * $sinphi);
                // L ranges from -sqrt(2) to +sqrt(2).  If it's < 0, the surface
                // is pointing away from us, so we won't bother trying to plot it
                if ($L > 0) {
                    // test against the z-buffer.  larger 1/z means the pixel is
                    // closer to the viewer than what's already plotted.
                    $zbuffer[$xp][$yp] = $zbuffer[$xp][$yp] ?? 0;
                    if ($ooz > $zbuffer[$xp][$yp]) {
                        $zbuffer[$xp][$yp] = $ooz;
                        $luminance_index = $L * 8;
                        // luminance_index is now in the range 0..11 (8*sqrt(2) = 11.3)
                        // now we lookup the character corresponding to the
                        // luminance and plot it in our output:
                        $output[$xp][$yp] = ".,-~:;=!*#$@"[$luminance_index];
                    }
                }
            }
        }

        $this->outputBuffer = $output;
    }

    private function output()
    {
        echo "\x1b[H";
        for ($j = 0; $j < $this->screenHeight; $j++) {
            echo "\t";
            for ($i = 0; $i < $this->screenWidth; $i++) {
                echo $this->outputBuffer[$i][$j] ?? ' ';
            }
            echo "\n";
        }
    }
}
