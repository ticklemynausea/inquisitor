/*
 * Copyright 2012 frdfsnlght <frdfsnlght@gmail.com>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
package com.frdfsnlght.inquisitor;

import java.io.IOException;

/**
 *
 * @author frdfsnlght <frdfsnlght@gmail.com>
 */


public abstract class WebHandler {

    public boolean matchesRequest(WebRequest request, TypeMap pathParams) {
        return true;
    }

    public abstract void handleRequest(WebRequest request, WebResponse response) throws IOException;

}
