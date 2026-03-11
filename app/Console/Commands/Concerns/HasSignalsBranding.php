<?php

namespace App\Console\Commands\Concerns;

trait HasSignalsBranding
{
    protected function displaySignalsLogo(): void
    {
        $logo = <<<'LOGO'

            <fg=blue>@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@</>
            <fg=blue>@@</>                                                                                                  <fg=gray>@@@@@@</>
            <fg=blue>@@</>                                                                                                  <fg=gray>@@@@@@</>
            <fg=blue>@@</>                                                                                                  <fg=gray>@@@@@@</>
            <fg=blue>@@</>                                                                                                       <fg=blue>@</>
            <fg=blue>@@</>                                                                                                       <fg=blue>@</>
            <fg=blue>@@</>               <fg=white>@@@@@@     @@     @@@@@@@    @@     @@       @@       @@         @@@@@@</>                 <fg=blue>@</>
            <fg=blue>@@</>              <fg=white>@@    @@@   @@    @@    @@@   @@@@   @@      @@@@      @@        @@    @@</>                <fg=blue>@</>
            <fg=blue>@@</>              <fg=white>@@          @@    @@          @@ @@  @@      @@ @@     @@        @@</>                      <fg=blue>@</>
            <fg=blue>@@</>               <fg=white>@@@@@@@    @@    @@  @@@@@   @@  @@ @@     @@  @@     @@         @@@@@@@</>                <fg=blue>@</>
            <fg=blue>@@</>                     <fg=white>@@   @@    @@     @@   @@   @@@@    @@@@@@@@    @@              @@@</>               <fg=blue>@</>
            <fg=blue>@@</>              <fg=white>@@@@@@@@@   @@    @@@@@@@@@   @@    @@@   @@@    @@@   @@@@@@@@  @@@@@@@@</>                <fg=blue>@</>
            <fg=blue>@@</>                                                                                                       <fg=blue>@</>
            <fg=blue>@@</>                                                                                                       <fg=blue>@</>
            <fg=blue>@@</>                                                                                                       <fg=blue>@</>
            <fg=blue>@@</>                                                                                                       <fg=blue>@</>
            <fg=blue>@@</>                                                                                                       <fg=blue>@</>
            <fg=blue>@@</>                                                                                                       <fg=blue>@</>
            <fg=blue>@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@</>

        LOGO;

        $this->line($logo);
    }
}
